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
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function count;
use function function_exists;
use function is_array;
use function is_int;
use function is_string;
use function range;

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
     * Decode a single decoded JSON value into a JSON-RPC value object.
     *
     * @param mixed $input
     * @return RpcMessageInterface
     * @throws RuntimeException when the value is not a valid JSON-RPC message
     */
    protected function decode($input) : RpcMessageInterface
    {
        if (!is_array($input)) {
            throw new RuntimeException('Decoded JSON-RPC message is not an object');
        }

        if (!isset($input['jsonrpc']) || $input['jsonrpc'] !== RpcMessageInterface::JSONRPC_VERSION) {
            throw new RuntimeException('Unknown or missing JSON-RPC version string');
        }

        if (isset($input['method'])) {
            /**
             * A missing `params` member means "no parameters", however a present `params`
             * member MUST be a structured value (array/object). `null` and scalars are
             * therefore rejected rather than silently coerced to an empty parameter list.
             */
            if (array_key_exists('params', $input)) {
                $params = $input['params'];
                if (!is_array($params)) {
                    throw new RuntimeException('Invalid JSON-RPC params: must be an array or object');
                }
            } else {
                $params = [];
            }

            // If the ID field is contained in the request even if NULL then we consider it to be Request
            if (array_key_exists('id', $input)) {
                return new Request($input['method'], $params, $input['id']);
            }

            return new Notification($input['method'], $params);
        }

        if (array_key_exists('result', $input)) {
            return new Response($input['id'] ?? null, $input['result']);
        }

        if (array_key_exists('error', $input)) {
            if (!is_array($input['error'])
                || !is_int($input['error']['code'] ?? null)
                || !is_string($input['error']['message'] ?? null)
            ) {
                throw new RuntimeException('Invalid JSON-RPC error object');
            }

            $error = new Error($input['error']['code'], $input['error']['message'],
                $input['error']['data'] ?? null);

            return new Response($input['id'] ?? null, $error);
        }

        throw new RuntimeException('Unable to decode json rpc packet, failed to identify Request, Response or Error record');
    }

    /**
     * Determine whether the decoded JSON value is a JSON array (list) rather than a
     * JSON object. `array_is_list()` is only available from PHP 8.1 while this library
     * supports PHP >= 8.0, hence the manual fallback.
     *
     * @param array $input
     * @return bool
     */
    protected static function isList(array $input) : bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($input);
        }

        return array_keys($input) === range(0, count($input) - 1);
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

            /**
             * An empty JSON array is not a batch and cannot be decoded into any
             * JSON-RPC message. Per the specification it is an Invalid Request that
             * must be answered with an error response carrying a null id.
             */
            if ([] === $input) {
                $this->emit('data', [new Response(null, new Error(Error::INVALID_REQUEST, Error::ERROR_MSG[Error::INVALID_REQUEST]))]);
                return;
            }

            /** A JSON object is a single message, a JSON array is a batch of messages */
            $isBatch = self::isList($input);
            if (!$isBatch) {
                $input = [$input];
            }

            /** Process responses whether batch or individual one by one and emit the jsonrpc */
            foreach ($input as $data) {
                try {
                    $jsonrpc = $this->decode($data);
                    $this->emit('data', [$jsonrpc]);
                } catch (Throwable $ex) {
                    /**
                     * A single malformed message (for example one invalid entry in a
                     * batch) MUST NOT abort the remaining messages or tear down the
                     * stream. Report it via the `error` event and carry on so callers
                     * can continue reading subsequent messages.
                     */
                    $this->emit('error', [$ex]);
                }
            }
        } catch (Throwable $ex) {
            /** Genuinely fatal input (for example a decoded scalar) closes the stream. */
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