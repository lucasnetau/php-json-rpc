<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC\React;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;
use RuntimeException;
use Clue\React\NDJson\Decoder as NDJsonDecoder;
use EdgeTelemetrics\JSON_RPC\Notification;
use EdgeTelemetrics\JSON_RPC\Request;
use EdgeTelemetrics\JSON_RPC\Response;
use EdgeTelemetrics\JSON_RPC\Error;

/**
 * The Decoder / Parser reads from a NDJSON stream and emits JSON-RPC notifications/requests/responses
 */
class Decoder extends EventEmitter implements ReadableStreamInterface
{
    /**
     * @var \Clue\React\NDJson\Decoder
     */
    protected $ndjson_decoder;

    private $closed = false;

    public function __construct(ReadableStreamInterface $input)
    {
        $this->ndjson_decoder = new NDJsonDecoder($input, true);

        $this->ndjson_decoder->on('data', array($this, 'handleData'));
        $this->ndjson_decoder->on('end', array($this, 'handleEnd'));
        $this->ndjson_decoder->on('error', array($this, 'handleError'));
        $this->ndjson_decoder->on('close', array($this, 'close'));
    }

    public function close()
    {
        $this->closed = true;
        $this->ndjson_decoder->close();
        $this->emit('close');
        $this->removeAllListeners();
    }

    public function isReadable()
    {
        return $this->ndjson_decoder->isReadable();
    }

    public function pause()
    {
        $this->ndjson_decoder->pause();
    }

    public function resume()
    {
        $this->ndjson_decoder->resume();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);
        return $dest;
    }

    //@TODO Handle json-rpc batch (array of data)
    public function handleData($data)
    {
        if (!isset($data['jsonrpc']))
        {
            throw new RuntimeException('Unable to decode. Missing required jsonrpc field');
        }

        if ($data['jsonrpc'] != Notification::JSONRPC_VERSION)
        {
            throw new RuntimeException('Unknown JSON-RPC version string');
        }

        if (isset($data['method']))
        {
            if (isset($data['id']))
            {
                $jsonrpc = new Request();
                $jsonrpc->setId($data['id']);
            }
            else
                {
                $jsonrpc = new Notification();
            }
            $jsonrpc->setMethod($data['method']);
            if (isset($data['params']))
            {
                $jsonrpc->setParams($data['params']);
            }
        }
        elseif (isset($data['result']) || isset($data['error']))
        {
            $jsonrpc = new Response();
            $jsonrpc->setId($data['id']);
            if (isset($data['result']))
            {
                $jsonrpc->setResult($data['result']);
            }
            else
            {
                $error = new Error($data['error']['code'], $data['error']['message']);
                if (isset($data['error']['data']))
                {
                    $error->setData($data['error']['data']);
                }
                $jsonrpc->setError($error);
            }
        }
        else
        {
            throw new RuntimeException('Unable to decode json rpc packet');
        }

        $this->emit('data', [$jsonrpc]);
    }

    /** @internal */
    public function handleEnd()
    {
        if (!$this->closed) {
            $this->emit('end');
            $this->close();
        }
    }

    /** @internal */
    public function handleError(\Exception $error)
    {
        $this->emit('error', array($error));
        $this->close();
    }
}