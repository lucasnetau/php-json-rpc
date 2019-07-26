<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC;

use JsonSerializable;
use RuntimeException;

use function is_float;
use function is_string;
use function is_int;
use function is_null;

/**
 * Class Request
 * @package EdgeTelemetrics\JSON_RPC
 *
 * Request extends Notification and includes the Id property
 */
class Request extends Notification implements JsonSerializable {

    /**
     * @var string|int|null An identifier established by the Client that MUST contain a String, Number, or NULL value
     */
    protected $id;

    /**
     * Request constructor.
     * @param string $method
     * @param array $params
     * @param null $id
     */
    public function __construct(string $method, array $params = [], $id = null)
    {
        parent::__construct($method, $params);
        $this->setId($id);
    }

    /**
     * Set the id for the request. This is used between the Client and Server to correlate requests with responses.
     * @param string|float|null $id
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
     * @return mixed
     */
    public function jsonSerialize()
    {
        $record = parent::jsonSerialize();
        $record['id'] = $this->id;
        return $record;
    }
}