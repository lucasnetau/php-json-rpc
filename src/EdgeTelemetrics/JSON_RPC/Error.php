<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC;

class Error implements \JsonSerializable
{
    /**
     * @var int
     */
    protected $code;

    /**
     * @var string
     */
    protected $message;

    protected $data;

    public function __construct(int $code, string $message, $data = null)
    {
        $this->setCode($code);
        $this->setMessage($message);
        $this->setData($data);
    }

    public function setCode(int $code)
    {
        $this->code = $code;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setMessage(string $message)
    {
        $this->message = $message;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    public function jsonSerialize()
    {
        $record = ['code' => $this->code,
            'message' => $this->message];

        if (null !== $this->data)
        {
            $record['data'] = $this->data;
        }

        return $record;
    }
}