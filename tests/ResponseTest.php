<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC\Tests;

use EdgeTelemetrics\JSON_RPC\Error;
use EdgeTelemetrics\JSON_RPC\Request;
use EdgeTelemetrics\JSON_RPC\Response;
use EdgeTelemetrics\JSON_RPC\RpcMessageInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

class ResponseTest extends TestCase
{
    public function testConstructorWithResult(): void
    {
        $response = new Response(1, 'pong');

        $this->assertSame(1, $response->getId());
        $this->assertSame('pong', $response->getResult());
        $this->assertTrue($response->isSuccess());
        $this->assertFalse($response->isError());
        $this->assertNull($response->getError());
    }

    public function testConstructorWithNullResultIsSuccess(): void
    {
        $response = new Response(null, null);

        $this->assertNull($response->getId());
        $this->assertNull($response->getResult());
        $this->assertTrue($response->isSuccess());
        $this->assertFalse($response->isError());
    }

    public function testConstructorWithErrorObject(): void
    {
        $error = new Error(Error::INTERNAL_ERROR, 'Internal error', ['detail' => 1]);
        $response = new Response(5, $error);

        $this->assertSame(5, $response->getId());
        $this->assertFalse($response->isSuccess());
        $this->assertTrue($response->isError());
        $this->assertSame($error, $response->getError());
        $this->assertSame($error, $response->getResult());
    }

    public function testCreateFromRequestInheritsIdAndResult(): void
    {
        $request = new Request('ping', [], 'req-id');
        $response = Response::createFromRequest($request, 'pong');

        $this->assertSame('req-id', $response->getId());
        $this->assertSame('pong', $response->getResult());
        $this->assertTrue($response->isSuccess());
    }

    public function testCreateFromRequestInheritsNullId(): void
    {
        $request = new Request('ping', [], null);
        $response = Response::createFromRequest($request);

        $this->assertNull($response->getId());
        $this->assertNull($response->getResult());
    }

    public function testSetResultReplacesErrorState(): void
    {
        $response = new Response(1, new Error(Error::PARSE_ERROR, 'Parse error'));
        $this->assertTrue($response->isError());

        $response->setResult('ok');

        $this->assertTrue($response->isSuccess());
        $this->assertSame('ok', $response->getResult());
        $this->assertNull($response->getError());
    }

    public function testSetErrorTurnsResponseIntoError(): void
    {
        $response = new Response(1, 'ok');
        $error = new Error(Error::INVALID_REQUEST, 'Invalid Request');
        $response->setError($error);

        $this->assertTrue($response->isError());
        $this->assertFalse($response->isSuccess());
        $this->assertSame($error, $response->getError());
    }

    public function testSetIdAcceptsStringIntFloatAndNull(): void
    {
        $response = new Response(1);

        $response->setId('id');
        $this->assertSame('id', $response->getId());

        $response->setId(7);
        $this->assertSame(7, $response->getId());

        $response->setId(7.8);
        $this->assertSame(7, $response->getId());

        $response->setId(null);
        $this->assertNull($response->getId());
    }

    public function testSetIdRejectsInfiniteFloat(): void
    {
        $response = new Response(1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Id format. Must be string, number or null');
        $response->setId(INF);
    }

    public function testSetIdRejectsArray(): void
    {
        $response = new Response(1);

        $this->expectException(RuntimeException::class);
        $response->setId([]);
    }

    public function testConstructorRejectsBooleanId(): void
    {
        $this->expectException(TypeError::class);
        new Response(true);
    }

    public function testJsonSerializeSuccessShape(): void
    {
        $response = new Response(1, 'pong');

        $this->assertSame(
            ['jsonrpc' => '2.0', 'id' => 1, 'result' => 'pong'],
            $response->jsonSerialize()
        );
    }

    public function testJsonSerializeErrorShape(): void
    {
        $response = new Response(1, new Error(Error::METHOD_NOT_FOUND, 'Method not found'));

        $serialized = $response->jsonSerialize();

        $this->assertSame('2.0', $serialized['jsonrpc']);
        $this->assertSame(1, $serialized['id']);
        $this->assertArrayNotHasKey('result', $serialized);
        $this->assertInstanceOf(Error::class, $serialized['error']);
        $this->assertSame(
            ['code' => Error::METHOD_NOT_FOUND, 'message' => 'Method not found'],
            $serialized['error']->jsonSerialize()
        );
    }

    public function testJsonEncodeRoundTripSuccess(): void
    {
        $response = new Response(1, ['a' => 1]);
        $decoded = json_decode(json_encode($response), true);

        $this->assertSame($response->jsonSerialize(), $decoded);
    }

    public function testJsonEncodeRoundTripError(): void
    {
        $response = new Response(3, new Error(Error::INVALID_PARAMS, 'Invalid params', ['field' => 'id']));
        $decoded = json_decode(json_encode($response), true);

        $this->assertSame(
            [
                'jsonrpc' => '2.0',
                'id' => 3,
                'error' => ['code' => Error::INVALID_PARAMS, 'message' => 'Invalid params', 'data' => ['field' => 'id']],
            ],
            $decoded
        );
    }

    public function testImplementsRpcMessageInterface(): void
    {
        $this->assertInstanceOf(RpcMessageInterface::class, new Response(1));
    }
}
