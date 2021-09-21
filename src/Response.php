<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC;

use RuntimeException;

use function is_float;
use function is_string;
use function is_int;
use function is_null;

/**
 * Class Response
 * @package EdgeTelemetrics\JSON_RPC
 */
class Response implements RpcMessageInterface {
    /**
     * @var string|int|null
     */
    protected $id;

    /** @var mixed $result  */
    protected $result = null;

    /**
     * Response constructor.
     * @param string|int|float|null $id
     * @param mixed|null $result
     */
    public function __construct($id, $result = null)
    {
        $this->setId($id);

        if ($result instanceof Error)
        {
            $this->setError($result);
        }
        else
        {
            $this->setResult($result);
        }
    }

    /**
     * Create a JSONRPC response from a request object
     * @param Request $request
     * @param mixed|null $result
     * @return Response
     */
    static public function createFromRequest(Request $request, $result = null): Response
    {
        return new self($request->getId(), $result);
    }

    /**
     * Set the id for the request. This is used between the Client and Server to correlate requests with responses.
     * @param string|int|float|null $id
     */
    public function setId($id)
    {
        /** JSONRPC Spec - Numbers SHOULD NOT contain fractional parts */
        if (is_float($id)) {
            $id = (int)$id;
        }
        /** String, Number, or NULL value  */
        if (is_string($id) || is_int($id) || is_null($id)) {
            $this->id = $id;
        } else {
            throw new RuntimeException('Invalid Id format. Must be string, number or null');
        }
    }

    /**
     * @return int|string|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $result
     */
    public function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return (!$this->isError());
    }

    /**
     * @param Error $error
     */
    public function setError(Error $error)
    {
        $this->result = $error;
    }

    /**
     * @return Error|void
     */
    public function getError() : Error
    {
        if ($this->isError()) {
            return $this->result;
        }
    }

    /**
     * @return bool
     */
    public function isError(): bool
    {
        return ($this->result instanceof Error);
    }

    /**
     * @return array
     */
    public function jsonSerialize() : array
    {
        $record = ['jsonrpc' => self::JSONRPC_VERSION];
        $record['id'] = $this->id;
        if ($this->isError()) {
            $record['error'] = $this->result;
        } else {
            $record['result'] = $this->result;
        }
        return $record;
    }
}