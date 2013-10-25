<?php

/*
 * This file is part of the h4cc/GuzzleRollingBatch package.
 *
 * (c) Julius Beckmann <github@h4cc.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace h4cc\GuzzleRollingBatch;

use Guzzle\Http\Curl\CurlHandle;
use Guzzle\Http\Exception\CurlException;
use Guzzle\Http\Message\RequestInterface;
use h4cc\GuzzleRollingBatch\Queue\RequestQueue;
use h4cc\GuzzleRollingBatch\Queue\RequestQueueInterface;
use h4cc\GuzzleRollingBatch\Queue\ResponseQueue;
use h4cc\GuzzleRollingBatch\Queue\ResponseQueueInterface;

/**
 * Class RollingBatch
 *
 * @author Julius Beckmann <github@h4cc.de>
 */
class RollingBatch implements RollingBatchInterface
{
    /**
     * Number of max parallel requests. 0 disables maximum.
     *
     * @var integer
     */
    private $number_parallel = 3;

    /**
     * Queue for incoming requests.
     *
     * @var Queue\RequestQueue
     */
    private $requestQueue;

    /**
     * Queue for received responses.
     *
     * @var Queue\ResponseQueue
     */
    private $responseQueue;

    /** @var resource cURL multi handle. */
    protected $multiHandle;

    /** @var \SplObjectStorage RequestInterface to CurlHandle hash */
    protected $handles;

    /** @var array Hash mapping curl handle resource IDs to request objects */
    protected $resourceHash = array();

    /** @var array cURL multi error values and codes */
    protected $multiErrors = array(
        CURLM_BAD_HANDLE => array('CURLM_BAD_HANDLE', 'The passed-in handle is not a valid CURLM handle.'),
        CURLM_BAD_EASY_HANDLE => array(
            'CURLM_BAD_EASY_HANDLE',
            "An easy handle was not good/valid. It could mean that it isn't an easy handle at all, or possibly that the handle already is in used by this or another multi handle."
        ),
        CURLM_OUT_OF_MEMORY => array('CURLM_OUT_OF_MEMORY', 'You are doomed.'),
        CURLM_INTERNAL_ERROR => array(
            'CURLM_INTERNAL_ERROR',
            'This can only be returned if libcurl bugs. Please report it to us!'
        )
    );

    public function __construct()
    {
        $this->setRequestQueue(new RequestQueue());
        $this->setResponseQueue(new ResponseQueue());

        $this->handles = new \SplObjectStorage();

        $this->initCurlMultiHandle();
    }

    /**
     * Sets number of max parallel requests.
     * 0 will disable maximum.
     *
     * @param $number integer
     * @throws \InvalidArgumentException
     */
    public function setNumberParallel($number)
    {
        if ($number < 1) {
            throw new \InvalidArgumentException('Number has to be > 0.');
        }
        $this->number_parallel = (integer)$number;
    }

    /**
     * @return integer
     */
    public function getNumberParallel()
    {
        return $this->number_parallel;
    }

    /**
     * Starts new requests if allowed and executes active requests.
     * Returns true if number of parallel requests is reached.
     *
     * @return bool
     */
    public function execute()
    {
        $this->startRequestsFromQueue();

        if ($this->countActive()) {
            $this->perform();
        }

        return ($this->getNumberParallel() != 0) && ($this->countActive() >= $this->getNumberParallel());
    }

    /**
     * @return RequestQueueInterface
     */
    public function getRequestQueue()
    {
        return $this->requestQueue;
    }

    /**
     * @param RequestQueueInterface $queue
     */
    public function setRequestQueue(RequestQueueInterface $queue)
    {
        $this->requestQueue = $queue;
    }

    /**
     * @return ResponseQueueInterface
     */
    public function getResponseQueue()
    {
        return $this->responseQueue;
    }

    /**
     * @param ResponseQueueInterface $queue
     */
    public function setResponseQueue(ResponseQueueInterface $queue)
    {
        $this->responseQueue = $queue;
    }

    /**
     * Returns number of currently active requests.
     *
     * @return int
     */
    public function countActive()
    {
        return $this->handles->count();
    }

    /**
     * Returns true, if batch has processed all requests and no active ones are outstanding.
     *
     * @return bool
     */
    public function isIdle()
    {
        return ($this->countActive() == 0 && $this->getRequestQueue()->isEmpty());
    }

    //--- Privates

    /**
     * Starts as much requests as $number_parallel allows.
     */
    protected function startRequestsFromQueue()
    {
        while (
        !$this->requestQueue->isEmpty() &&
        // Either start all when no maximum set, or only till maximum is reached.
        ($this->number_parallel == 0 || $this->countActive() < $this->number_parallel)
        ) {
            $request = $this->requestQueue->next();
            $request->setState(RequestInterface::STATE_TRANSFER);
            $handle = $this->createCurlHandle($request)->getHandle();
            $this->checkCurlResult(curl_multi_add_handle($this->multiHandle, $handle));
        }
    }

    /**
     * Process multi handle as long as $number_parallel requests are active
     */
    protected function perform()
    {
        // The first curl_multi_select often times out no matter what, but is usually required for fast transfers
        $selectTimeout = 0.001;
        // Limit to 100 exec calls here.
        $max = 100;
        do {
            do {
                $mrc = curl_multi_exec($this->multiHandle, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

            $this->checkCurlResult($mrc);

            $this->processMessages();

            if ($active && curl_multi_select($this->multiHandle, $selectTimeout) === -1) {
                // Perform a usleep if a select returns -1: https://bugs.php.net/bug.php?id=61141
                usleep(150);
            }

            // Set select timeout to 100 milliseconds to save CPU cycles.
            $selectTimeout = 0.1;
        } while (
            // Limit number of iterations
        --$max > 0 &&
        // It there is a limit and it is reached, process till we get under the limit again.
        (($this->number_parallel != 0) && ($this->handles->count() >= $this->number_parallel))
        );
    }

    /**
     * Process any received curl multi messages
     */
    protected function processMessages()
    {
        while ($done = curl_multi_info_read($this->multiHandle)) {
            $request = $this->resourceHash[(int)$done['handle']];
            $this->processResponse($request, $this->handles[$request], $done);
        }
    }

    /**
     * Check for errors and fix headers of a request based on a curl response
     *
     * @param RequestInterface $request Request to process
     * @param CurlHandle $handle Curl handle object
     * @param array $curl Array returned from curl_multi_info_read
     *
     * @throws CurlException on Curl error
     */
    protected function processResponse(RequestInterface $request, CurlHandle $handle, array $curl)
    {
        // Set the transfer stats on the response
        $handle->updateRequestFromTransfer($request);
        // Check if a cURL exception occurred, and if so, notify things
        $curlException = $this->isCurlException($request, $handle, $curl);

        // Always remove completed curl handles. They can be added back again
        // via events if needed (e.g. ExponentialBackoffPlugin)
        $this->removeHandle($request);

        // Removed some code from CurlMulti, so the must Plugins might not work.

        if ($curlException) {
            $request->setState(RequestInterface::STATE_ERROR, array('exception' => $curlException));
        } else {
            $request->setState(RequestInterface::STATE_COMPLETE, array('handle' => $handle));
        }

        $response = $request->getResponse();
        if ($response) {
            $this->responseQueue->add($response);
        }
    }

    /**
     * Remove a curl handle from the curl multi object
     *
     * @param RequestInterface $request Request that owns the handle
     */
    protected function removeHandle(RequestInterface $request)
    {
        if (isset($this->handles[$request])) {
            $handle = $this->handles[$request];
            curl_multi_remove_handle($this->multiHandle, $handle->getHandle());
            unset($this->handles[$request]);
            unset($this->resourceHash[(int)$handle->getHandle()]);
            $handle->close();
        }
    }

    /**
     * Check if a cURL transfer resulted in what should be an exception
     *
     * @param RequestInterface $request Request to check
     * @param CurlHandle $handle Curl handle object
     * @param array $curl Array returned from curl_multi_info_read
     *
     * @return CurlException|bool
     */
    protected function isCurlException(RequestInterface $request, CurlHandle $handle, array $curl)
    {
        if (CURLM_OK == $curl['result'] || CURLM_CALL_MULTI_PERFORM == $curl['result']) {
            return false;
        }

        $handle->setErrorNo($curl['result']);
        $exception = new CurlException(sprintf(
            '[curl] %s: %s [url] %s',
            $handle->getErrorNo(),
            $handle->getError(),
            $handle->getUrl()
        ));
        $exception->setCurlHandle($handle)
        ->setRequest($request)
        ->setCurlInfo($handle->getInfo())
        ->setError($handle->getError(), $handle->getErrorNo());

        return $exception;
    }

    /**
     * Create a curl handle for a request
     *
     * @param RequestInterface $request Request
     *
     * @return CurlHandle
     */
    protected function createCurlHandle(RequestInterface $request)
    {
        $wrapper = CurlHandle::factory($request);
        $this->handles[$request] = $wrapper;
        $this->resourceHash[(int)$wrapper->getHandle()] = $request;

        return $wrapper;
    }

    /**
     * Throw an exception for a cURL multi response if needed
     *
     * @param int $code Curl response code
     * @throws CurlException
     */
    protected function checkCurlResult($code)
    {
        if ($code != CURLM_OK && $code != CURLM_CALL_MULTI_PERFORM) {
            throw new CurlException(isset($this->multiErrors[$code])
                ? "cURL error: {$code} ({$this->multiErrors[$code][0]}): cURL message: {$this->multiErrors[$code][1]}"
                : 'Unexpected cURL error: ' . $code
            );
        }
    }

    /**
     * Initializes the curl multi handle.
     *
     * @throws \Guzzle\Http\Exception\CurlException
     */
    protected function initCurlMultiHandle()
    {
        $this->multiHandle = curl_multi_init();
        // @codeCoverageIgnoreStart
        if ($this->multiHandle === false) {
            throw new CurlException('Unable to create multi handle');
        }
        // @codeCoverageIgnoreEnd
    }
}