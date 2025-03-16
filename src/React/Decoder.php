<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC\React;

use EdgeTelemetrics\JSON_RPC\RpcMessageInterface;
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
use Throwable;
use function array_key_exists;
use function is_array;

/**
 * The Decoder / Parser reads from a NDJSON stream and emits JSON-RPC notifications/requests/responses
 */
class Decoder extends EventEmitter implements ReadableStreamInterface
{
    /**
     * @var NDJsonDecoder
     */
    protected NDJsonDecoder $ndjson_decoder;

    /**
     * @var bool Flag if stream is closed
     */
    private bool $closed = false;

    /**
     * Decoder constructor.
     * @param ReadableStreamInterface $input
     * @param int $maxLength Max length of a JSON line
     */
    public function __construct(ReadableStreamInterface $input, int $maxLength = 65536)
    {
        $this->ndjson_decoder = new NDJsonDecoder($input, true, 512, 0, $maxLength);

        $this->ndjson_decoder->on('data', array($this, 'handleData'));
        $this->ndjson_decoder->on('end', array($this, 'handleEnd'));
        $this->ndjson_decoder->on('error', array($this, 'handleError'));
        $this->ndjson_decoder->on('close', array($this, 'close'));
    }

    /**
     * Close the stream
     */
    public function close() : void
    {
        $this->closed = true;
        $this->ndjson_decoder->close();
        $this->emit('close');
        $this->removeAllListeners();
    }

    /**
     * @return bool
     */
    public function isReadable() : bool
    {
        return $this->ndjson_decoder->isReadable();
    }

    /**
     * Pause
     */
    public function pause() : void
    {
        $this->ndjson_decoder->pause();
    }

    /**
     * Resume
     */
    public function resume() : void
    {
        $this->ndjson_decoder->resume();
    }

    /**
     * Pipe output between up and $dest
     * @param WritableStreamInterface $dest
     * @param array $options
     * @return WritableStreamInterface
     */
    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);
        return $dest;
    }

    /**
     * @param $input
     */
    public function handleData($input)
    {
        try {
            if (!is_array($input)) {
                throw new RuntimeException('Decoded JSON data is not an array');
            }

            /** Check if we are batch request */
            //if (!array_is_list($input))
            if (!array_key_exists(0, $input)) {
                $input = [$input];
            }

            /** Process responses whether batch or individual one by one and emit the jsonrpc */
            foreach ($input as $data) {
                if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== RpcMessageInterface::JSONRPC_VERSION) {
                    throw new RuntimeException('Unknown or missing JSON-RPC version string');
                }

                if (isset($data['method'])) {
                    // If the ID field is contained in the request even if NULL then we consider it to be Request
                    if (isset($data['id']) || array_key_exists('id', $data)) {
                        $jsonrpc = new Request($data['method'], $data['params'] ?? [], $data['id']);
                    } else {
                        $jsonrpc = new Notification($data['method'], $data['params'] ?? []);
                    }
                } elseif (isset($data['result'])) {
                    $jsonrpc = new Response($data['id'], $data['result']);
                } elseif (isset($data['error'])) {
                    $error = new Error($data['error']['code'], $data['error']['message'],
                        $data['error']['data'] ?? null);
                    $jsonrpc = new Response($data['id'], $error);
                } else {
                    throw new RuntimeException('Unable to decode json rpc packet, failed to identify Request, Response or Error record');
                }
                $this->emit('data', [$jsonrpc]);
            }
        } catch (Throwable $ex) {
            $this->handleError($ex);
        }
    }

    /** @internal */
    public function handleEnd()
    {
        if (!$this->closed) {
            $this->emit('end');
            $this->close();
        }
    }

    /**
     * @param Throwable $error
     * @internal
     */
    public function handleError(Throwable $error)
    {
        $this->emit('error', array($error));
        $this->close();
    }
}