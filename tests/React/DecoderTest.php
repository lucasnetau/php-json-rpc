<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC\Tests\React;

use EdgeTelemetrics\JSON_RPC\Error;
use EdgeTelemetrics\JSON_RPC\Notification;
use EdgeTelemetrics\JSON_RPC\React\Decoder;
use EdgeTelemetrics\JSON_RPC\Request;
use EdgeTelemetrics\JSON_RPC\Response;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\Stream\ThroughStream;
use RuntimeException;
use Throwable;

class DecoderTest extends TestCase
{
    /** @var array{data: array, error: array, end: int, close: int} */
    private array $events = [];

    private function makeDecoder(?ThroughStream $input = null): Decoder
    {
        $this->events = ['data' => [], 'error' => [], 'end' => 0, 'close' => 0];
        $decoder = new Decoder($input ?? new ThroughStream());

        $decoder->on('data', function ($message): void {
            $this->events['data'][] = $message;
        });
        $decoder->on('error', function (Throwable $error): void {
            $this->events['error'][] = $error;
        });
        $decoder->on('end', function (): void {
            $this->events['end']++;
        });
        $decoder->on('close', function (): void {
            $this->events['close']++;
        });

        return $decoder;
    }

    public function testDecodeRequest(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData(['jsonrpc' => '2.0', 'method' => 'ping', 'params' => ['a' => 1], 'id' => 7]);

        $this->assertCount(1, $this->events['data']);
        $message = $this->events['data'][0];
        $this->assertInstanceOf(Request::class, $message);
        $this->assertSame('ping', $message->getMethod());
        $this->assertSame(['a' => 1], $message->getParams());
        $this->assertSame(7, $message->getId());
        $this->assertSame([], $this->events['error']);
    }

    public function testDecodeRequestWithoutParams(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData(['jsonrpc' => '2.0', 'method' => 'ping', 'id' => 1]);

        $this->assertInstanceOf(Request::class, $this->events['data'][0]);
        $this->assertSame([], $this->events['data'][0]->getParams());
    }

    public function testDecodeRequestWithNullIdIsStillARequest(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData(['jsonrpc' => '2.0', 'method' => 'ping', 'id' => null]);

        $this->assertInstanceOf(Request::class, $this->events['data'][0]);
        $this->assertNull($this->events['data'][0]->getId());
    }

    public function testDecodeNotification(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData(['jsonrpc' => '2.0', 'method' => 'notify', 'params' => [1]]);

        $this->assertCount(1, $this->events['data']);
        $message = $this->events['data'][0];
        $this->assertInstanceOf(Notification::class, $message);
        $this->assertNotInstanceOf(Request::class, $message);
        $this->assertSame('notify', $message->getMethod());
        $this->assertSame([1], $message->getParams());
    }

    public function testDecodeSuccessResponse(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData(['jsonrpc' => '2.0', 'id' => 3, 'result' => 'pong']);

        $this->assertCount(1, $this->events['data']);
        $message = $this->events['data'][0];
        $this->assertInstanceOf(Response::class, $message);
        $this->assertSame(3, $message->getId());
        $this->assertTrue($message->isSuccess());
        $this->assertSame('pong', $message->getResult());
    }

    public function testDecodeSuccessResponseWithNullResult(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData(['jsonrpc' => '2.0', 'id' => 3, 'result' => null]);

        $this->assertInstanceOf(Response::class, $this->events['data'][0]);
        $this->assertTrue($this->events['data'][0]->isSuccess());
        $this->assertNull($this->events['data'][0]->getResult());
    }

    public function testDecodeErrorResponse(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData([
            'jsonrpc' => '2.0',
            'id' => 4,
            'error' => ['code' => Error::METHOD_NOT_FOUND, 'message' => 'Method not found', 'data' => ['m' => 'x']],
        ]);

        $this->assertCount(1, $this->events['data']);
        $message = $this->events['data'][0];
        $this->assertInstanceOf(Response::class, $message);
        $this->assertSame(4, $message->getId());
        $this->assertTrue($message->isError());
        $this->assertFalse($message->isSuccess());
        $this->assertInstanceOf(Error::class, $message->getError());
        $this->assertSame(Error::METHOD_NOT_FOUND, $message->getError()->getCode());
        $this->assertSame('Method not found', $message->getError()->getMessage());
        $this->assertSame(['m' => 'x'], $message->getError()->getData());
        $this->assertSame([], $this->events['error']);
    }

    public function testDecodeErrorResponseWithoutData(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData([
            'jsonrpc' => '2.0',
            'id' => 4,
            'error' => ['code' => Error::PARSE_ERROR, 'message' => 'Parse error'],
        ]);

        $this->assertInstanceOf(Response::class, $this->events['data'][0]);
        $this->assertNull($this->events['data'][0]->getError()->getData());
    }

    public function testDecodeBatchEmitsEachMessage(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData([
            ['jsonrpc' => '2.0', 'method' => 'ping', 'id' => 1],
            ['jsonrpc' => '2.0', 'method' => 'notify'],
            ['jsonrpc' => '2.0', 'id' => 1, 'result' => 'pong'],
            ['jsonrpc' => '2.0', 'id' => 2, 'error' => ['code' => -32601, 'message' => 'Method not found']],
        ]);

        $this->assertCount(4, $this->events['data']);
        $this->assertInstanceOf(Request::class, $this->events['data'][0]);
        $this->assertInstanceOf(Notification::class, $this->events['data'][1]);
        $this->assertNotInstanceOf(Request::class, $this->events['data'][1]);
        $this->assertInstanceOf(Response::class, $this->events['data'][2]);
        $this->assertTrue($this->events['data'][2]->isSuccess());
        $this->assertInstanceOf(Response::class, $this->events['data'][3]);
        $this->assertTrue($this->events['data'][3]->isError());
        $this->assertSame([], $this->events['error']);
    }

    public function testMalformedNonArrayInputEmitsErrorAndCloses(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData('not-an-array');

        $this->assertCount(1, $this->events['error']);
        $this->assertInstanceOf(RuntimeException::class, $this->events['error'][0]);
        $this->assertFalse($decoder->isReadable());
        $this->assertSame(1, $this->events['close']);
    }

    public function testMissingJsonrpcVersionEmitsErrorAndCloses(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData(['method' => 'ping', 'id' => 1]);

        $this->assertCount(1, $this->events['error']);
        $this->assertStringContainsString('JSON-RPC version', $this->events['error'][0]->getMessage());
        $this->assertFalse($decoder->isReadable());
    }

    public function testWrongJsonrpcVersionEmitsErrorAndCloses(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData(['jsonrpc' => '1.0', 'method' => 'ping', 'id' => 1]);

        $this->assertCount(1, $this->events['error']);
        $this->assertFalse($decoder->isReadable());
    }

    public function testUnknownMessageTypeEmitsErrorAndCloses(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData(['jsonrpc' => '2.0', 'something' => 'else']);

        $this->assertCount(1, $this->events['error']);
        $this->assertStringContainsString('failed to identify', $this->events['error'][0]->getMessage());
        $this->assertSame([], $this->events['data']);
        $this->assertFalse($decoder->isReadable());
    }

    public function testInvalidParamsEmitsErrorAndCloses(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData(['jsonrpc' => '2.0', 'method' => 'ping', 'params' => 'oops', 'id' => 1]);

        $this->assertCount(1, $this->events['error']);
        $this->assertStringContainsString('params', $this->events['error'][0]->getMessage());
        $this->assertFalse($decoder->isReadable());
    }

    public function testInvalidErrorObjectEmitsErrorAndCloses(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData(['jsonrpc' => '2.0', 'id' => 1, 'error' => ['message' => 'missing code']]);

        $this->assertCount(1, $this->events['error']);
        $this->assertStringContainsString('Invalid JSON-RPC error object', $this->events['error'][0]->getMessage());
        $this->assertFalse($decoder->isReadable());
    }

    public function testBatchContainingNonArrayElementEmitsErrorAndCloses(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleData([['jsonrpc' => '2.0', 'method' => 'ping', 'id' => 1], 'oops']);

        $this->assertCount(1, $this->events['data']);
        $this->assertCount(1, $this->events['error']);
        $this->assertFalse($decoder->isReadable());
    }

    public function testCloseEmitsCloseAndMakesUnreadable(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->close();

        $this->assertSame(1, $this->events['close']);
        $this->assertFalse($decoder->isReadable());
    }

    public function testHandleEndEmitsEndAndCloses(): void
    {
        $decoder = $this->makeDecoder();
        $decoder->handleEnd();

        $this->assertSame(1, $this->events['end']);
        $this->assertSame(1, $this->events['close']);
        $this->assertFalse($decoder->isReadable());
    }

    public function testPauseAndResumeDelegateToUnderlyingStream(): void
    {
        $decoder = $this->makeDecoder();

        $this->assertNull($decoder->pause());
        $this->assertNull($decoder->resume());
        $this->assertTrue($decoder->isReadable());
    }

    public function testPipeReturnsDestination(): void
    {
        $decoder = $this->makeDecoder();
        $dest = new ThroughStream();

        $this->assertSame($dest, $decoder->pipe($dest));
    }

    public function testEndToEndThroughStreamWithEventLoop(): void
    {
        $input = new ThroughStream();
        $decoder = $this->makeDecoder($input);

        $input->write("{\"jsonrpc\":\"2.0\",\"method\":\"ping\",\"id\":1}\n");
        $input->write("{\"jsonrpc\":\"2.0\",\"id\":1,\"result\":\"pong\"}\n");
        $input->end("{\"jsonrpc\":\"2.0\",\"method\":\"notify\"}\n");

        Loop::run();

        $this->assertCount(3, $this->events['data']);
        $this->assertInstanceOf(Request::class, $this->events['data'][0]);
        $this->assertInstanceOf(Response::class, $this->events['data'][1]);
        $this->assertInstanceOf(Notification::class, $this->events['data'][2]);
        $this->assertNotInstanceOf(Request::class, $this->events['data'][2]);
        $this->assertSame([], $this->events['error']);
        $this->assertSame(1, $this->events['end']);
        $this->assertFalse($decoder->isReadable());
    }

    public function testEndToEndMalformedLineEmitsError(): void
    {
        $input = new ThroughStream();
        $decoder = $this->makeDecoder($input);

        $input->end("{\"jsonrpc\":\"2.0\",\"method\":\"ping\",\"id\":1,}\n");

        Loop::run();

        $this->assertSame([], $this->events['data']);
        $this->assertCount(1, $this->events['error']);
        $this->assertFalse($decoder->isReadable());
    }
}
