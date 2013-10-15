GuzzleRollingBatch
===================

This is a parallel Executor for guzzle that can process multiple requests in parallel.
Instead of processing a batch of requests, there are two queues for requests and responses for dynamic handling
of execution, adding new requests and processing responses.


## Introduction

When building a crawler with a batch system, there are multiple problems.
Using batches wastes time when waiting for the result of the last request.
There is no fully parallel processing of responses, adding and preparing new requests.
When starting a batch, the result is more like a tiny DOS for the target site.
To avoid all of these problems, RollingBatch was created.


## Installation

Simply require the package by its name with composer:
```bash
$ php composer.phar require h4cc/guzzle-rolling-batch
```
Follow the 'dev-master' branch for latest dev version. But i recommend to use more stable version tags if available.


## Usage

The RollingBatch consists of three parts. The RequestQueue, ResponseQueue and the RollingBatch.
Lets have a look at each part.


### RequestQueue

New requests have to be added to the RequestQueue.
From there, the RollingBatch fetches them with the 'next()' method.

In case you need a more flexible approach, implement your own RequestQueue.


### ResponseQueue

Received responses will be added to the ResponseQueue. From there they can be fetched and processed if needed.

Also, implementing your own ResponseQueue is an option for further functionality.


### RollingBatch

The RollingBatch has some methods for controlling the process.
Run 'execute()' to start new requests and execute started ones.
Getting the current status can be fetched with 'countActive()' and 'isIdle()'.

A example for processing one or more request at once
```php
$request = new Request('GET', 'http://example.com/');
$request->getCurlOptions()->set(CURLOPT_TIMEOUT_MS, 1000); // 1 Second

$batch = new RollingBatch();
$batch->getRequestQueue()->add($request);

do {
    // Calling execute once will _not_ guarantee to finish all started requests.
    $batch->execute();
} while (!$batch->isIdle());

$response = $batch->getResponseQueue()->next();
```

Be aware, that countActive() alone might not be enough as a predicate for loops etc.

It is also possible, to limit the current executed parallel requests.
This can be done with 'setNumberParallel()'. Setting the value '0' will disable the limit.
The number of parallel requests can also be reduced while running.


### Events

It is also possible to use the guzzle request events.

For example listening for exceptions.

```php
$request->getEventDispatcher()->addListener('request.exception', function(Event $event) {
    throw $event['exception'];
});
```

For more events have a look here:
http://guzzlephp.org/http-client/request.html#events-emitted-from-a-request
