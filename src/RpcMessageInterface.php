<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC;

use JsonSerializable;

/**
 * Interface for typehinting in functions that we want a JsonRpc object (request,notification,response)
 */
interface RpcMessageInterface extends JsonSerializable {}
