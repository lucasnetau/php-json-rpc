<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC;

class Response implements \JsonSerializable {

    const JSONRPC_VERSION = '2.0';

    /**
     * @var string|int|null
     */
    protected $id;

    protected $result;

    /**
     * @var Error
     */
    protected $error;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setResult($result)
    {
        $this->result = $result;
        unset($this->error); //Error must not exist if call is successful
    }

    public function getResult()
    {
        return $this->result;
    }

    public function isSuccess()
    {
        return (null !== $this->result);
    }

    public function setError(Error $error)
    {
        $this->error = $error;
        unset($this->result); //Result must not exist if an error is set
    }

    public function getError()
    {
        return $this->error;
    }

    public function isError()
    {
        return (null !== $this->error);
    }

    public function jsonSerialize()
    {
        $record = ['jsonrpc' => self::JSONRPC_VERSION];
        if (null !== $this->id)
        {
            $record['id'] = $this->id;
        }
        if (null !== $this->result)
        {
            $record['result'] = $this->result;
        }
        elseif(null !== $this->error)
        {
            $record['error'] = $this->error;
        }
        else
        {
            throw new \RuntimeException('Response must be successful or error state');
        }
        return $record;
    }
}