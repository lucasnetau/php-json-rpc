<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC;

use JsonSerializable;

/**
 * Class Error - Representation of RPC Error
 * @package EdgeTelemetrics\JSON_RPC
 */
class Error implements JsonSerializable
{
    /** Reserved error codes */
    const PARSE_ERROR = -32700;
    const INVALID_REQUEST = -32600;
    const METHOD_NOT_FOUND = -32601;
    const INVALID_PARAMS = -32602;
    const INTERNAL_ERROR = -32603;

    const ERROR_MSG = [
        self::PARSE_ERROR => "Parse error",
        self::INVALID_REQUEST => "Invalid Request",
        self::METHOD_NOT_FOUND => "Method not found",
        self::INVALID_PARAMS => "Invalid params",
        self::INTERNAL_ERROR => "Internal error",
    ];

    /** @var int A Number that indicates the error type that occurred. */
    protected int $code;

    /** @var string A String providing a short description of the error. */
    protected string $message;

    /** @var mixed Additional information about the error */
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

    public function getCode(): int
    {
        return $this->code;
    }

    public function setMessage(string $message)
    {
        $this->message = $message;
    }

    public function getMessage(): string
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

    public function jsonSerialize() : array
    {
        $record = [
            'code' => $this->code,
            'message' => $this->message
        ];

        if (null !== $this->data)
        {
            $record['data'] = $this->data;
        }

        return $record;
    }
}