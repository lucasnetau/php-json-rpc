<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC;

class Notification implements \JsonSerializable {

    const JSONRPC_VERSION = '2.0';

    /**
     * @var string
     */
    protected $method = '';

    /**
     * @var array
     */
    protected $params = [];

    public function setMethod(string $method)
    {
        $this->method = $method;
    }

    public function getMethod() : string
    {
        return $this->method;
    }

    public function setParams(array $params)
    {
        $this->params = $params;
    }

    public function setParam(string $name, $value)
    {
        $this->params[$name] = $value;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function getParam(string $name)
    {
        return $this->params[$name];
    }

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