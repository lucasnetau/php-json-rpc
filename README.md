# JSON RPC Helper Libraries for PHP

This library contains classes to construct JSON RPC notification, request, response and error objects.

Additionally there is a ReactPHP stream decoder included which will process JSON RPC request and responses encoded via NDJSON.

https://www.jsonrpc.org/specification

# Quickstart

## Create a Request on the client
```php
<?php
use EdgeTelemetrics\JSON_RPC\Request;
$request = new Request('ping', [], 'requestId');

$packet = json_encode($request);

// Send $packet to Server
````

# Server side
```php
<?php
//Process request

// Create the response from the request to pre-fill ID
$response = new Response::createFromRequest($request);
$response->setResult('pong');

$packet = json_encode($response);

// Send $packet back to Client

```