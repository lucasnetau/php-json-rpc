<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC\Tests;

use EdgeTelemetrics\JSON_RPC\Error;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class ErrorTest extends TestCase
{
    public function testConstructorSetsCodeMessageAndNullDataByDefault(): void
    {
        $error = new Error(Error::INTERNAL_ERROR, 'Internal error');

        $this->assertSame(Error::INTERNAL_ERROR, $error->getCode());
        $this->assertSame('Internal error', $error->getMessage());
        $this->assertNull($error->getData());
    }

    public function testReservedErrorCodes(): void
    {
        $this->assertSame(-32700, Error::PARSE_ERROR);
        $this->assertSame(-32600, Error::INVALID_REQUEST);
        $this->assertSame(-32601, Error::METHOD_NOT_FOUND);
        $this->assertSame(-32602, Error::INVALID_PARAMS);
        $this->assertSame(-32603, Error::INTERNAL_ERROR);
    }

    public function testReservedErrorMessageMap(): void
    {
        $this->assertSame('Parse error', Error::ERROR_MSG[Error::PARSE_ERROR]);
        $this->assertSame('Invalid Request', Error::ERROR_MSG[Error::INVALID_REQUEST]);
        $this->assertSame('Method not found', Error::ERROR_MSG[Error::METHOD_NOT_FOUND]);
        $this->assertSame('Invalid params', Error::ERROR_MSG[Error::INVALID_PARAMS]);
        $this->assertSame('Internal error', Error::ERROR_MSG[Error::INTERNAL_ERROR]);
    }

    public function testGettersAndSetters(): void
    {
        $error = new Error(1, 'one');

        $error->setCode(99);
        $error->setMessage('ninety nine');
        $error->setData(['a' => 1]);

        $this->assertSame(99, $error->getCode());
        $this->assertSame('ninety nine', $error->getMessage());
        $this->assertSame(['a' => 1], $error->getData());
    }

    public function testJsonSerializeOmitsDataWhenNull(): void
    {
        $error = new Error(Error::PARSE_ERROR, 'Parse error');

        $this->assertSame(
            ['code' => Error::PARSE_ERROR, 'message' => 'Parse error'],
            $error->jsonSerialize()
        );
    }

    public function testJsonSerializeIncludesDataWhenNotNull(): void
    {
        $error = new Error(Error::INVALID_PARAMS, 'Invalid params', ['field' => 'id']);

        $this->assertSame(
            ['code' => Error::INVALID_PARAMS, 'message' => 'Invalid params', 'data' => ['field' => 'id']],
            $error->jsonSerialize()
        );
    }

    public function testJsonSerializeIncludesExplicitNullDataValue(): void
    {
        $error = new Error(1, 'msg', null);

        $this->assertSame(
            ['code' => 1, 'message' => 'msg', 'data' => null],
            $error->jsonSerialize()
        );
    }

    public function testJsonSerializeOmitsDataWhenNotProvided(): void
    {
        $error = new Error(1, 'msg');

        $this->assertArrayNotHasKey('data', $error->jsonSerialize());
    }

    public function testJsonEncodeRoundTripMatchesSerializedShape(): void
    {
        $error = new Error(Error::INTERNAL_ERROR, 'Internal error', [1, 2, 3]);
        $decoded = json_decode(json_encode($error), true);

        $this->assertSame($error->jsonSerialize(), $decoded);
    }

    public function testImplementsJsonSerializable(): void
    {
        $this->assertInstanceOf(JsonSerializable::class, new Error(1, 'msg'));
    }
}
