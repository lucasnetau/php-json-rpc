<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC;

use JsonSerializable;

/**
 * Class Notification
 * @package EdgeTelemetrics\JSON_RPC
 */
class Notification implements JsonSerializable {

    /** @var string JSONRPC Version String */
    const JSONRPC_VERSION = '2.0';

    /**
     * @var string A String containing the name of the method to be invoked
     */
    protected $method = '';

    /**
     * @var array A Structured value that holds the parameter values to be used during the invocation of the method
     */
    protected $params = [];

    /**
     * Notification constructor.
     * @param string $method
     * @param array $params
     */
    public function __construct(string $method, array $params = [])
    {
        $this->setMethod($method);
        $this->setParams($params);
    }

    /**
     * Set RPC method to be invoked
     * @param string $method
     */
    public function setMethod(string $method)
    {
        $this->method = $method;
    }

    /**
     * Get RPC method to be invoked
     * @return string
     */
    public function getMethod() : string
    {
        return $this->method;
    }

    /**
     * Set and replace parameters
     * @param array $params
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }

    /**
     * Set an single parameter
     * @param string $name
     * @param $value
     */
    public function setParam(string $name, $value)
    {
        $this->params[$name] = $value;
    }

    /**
     * Get all parameters
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Get parameter value by name
     * @param string $name
     * @return mixed
     */
    public function getParam(string $name)
    {
        return $this->params[$name];
    }

    /**
     * @return mixed
     */
    public function jsonSerialize()
    {
        $record = ['jsonrpc' => self::JSONRPC_VERSION,
            'method' => $this->method];
        if (!empty($this->params))
        {
            $record['params'] = $this->params;
        }
        return $record;
    }
}