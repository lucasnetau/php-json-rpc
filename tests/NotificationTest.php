<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC\Tests;

use EdgeTelemetrics\JSON_RPC\Notification;
use EdgeTelemetrics\JSON_RPC\RpcMessageInterface;
use PHPUnit\Framework\TestCase;

class NotificationTest extends TestCase
{
    public function testConstructorSetsMethodAndDefaultsParams(): void
    {
        $notification = new Notification('ping');

        $this->assertSame('ping', $notification->getMethod());
        $this->assertSame([], $notification->getParams());
    }

    public function testConstructorSetsProvidedParams(): void
    {
        $notification = new Notification('sum', [1, 2, 3]);

        $this->assertSame([1, 2, 3], $notification->getParams());
    }

    public function testSetMethodReplacesMethod(): void
    {
        $notification = new Notification('ping');
        $notification->setMethod('pong');

        $this->assertSame('pong', $notification->getMethod());
    }

    public function testSetParamsReplacesAllParams(): void
    {
        $notification = new Notification('ping', ['a' => 1]);
        $notification->setParams(['b' => 2]);

        $this->assertSame(['b' => 2], $notification->getParams());
    }

    public function testSetParamAddsOrReplacesSingleParam(): void
    {
        $notification = new Notification('ping', ['a' => 1]);
        $notification->setParam('a', 2);
        $notification->setParam('b', 3);

        $this->assertSame(['a' => 2, 'b' => 3], $notification->getParams());
    }

    public function testGetParamReturnsValueOrNullWhenMissing(): void
    {
        $notification = new Notification('ping', ['a' => 1]);

        $this->assertSame(1, $notification->getParam('a'));
        $this->assertNull($notification->getParam('missing'));
    }

    public function testJsonSerializeOmitsEmptyParams(): void
    {
        $notification = new Notification('ping');

        $this->assertSame(
            ['jsonrpc' => RpcMessageInterface::JSONRPC_VERSION, 'method' => 'ping'],
            $notification->jsonSerialize()
        );
    }

    public function testJsonSerializeIncludesNonEmptyParams(): void
    {
        $notification = new Notification('sum', [1, 2]);

        $this->assertSame(
            ['jsonrpc' => '2.0', 'method' => 'sum', 'params' => [1, 2]],
            $notification->jsonSerialize()
        );
    }

    public function testJsonEncodeRoundTripMatchesSerializedShape(): void
    {
        $notification = new Notification('sum', ['a' => 1]);
        $decoded = json_decode(json_encode($notification), true);

        $this->assertSame($notification->jsonSerialize(), $decoded);
    }

    public function testImplementsRpcMessageInterface(): void
    {
        $this->assertInstanceOf(RpcMessageInterface::class, new Notification('ping'));
    }
}
