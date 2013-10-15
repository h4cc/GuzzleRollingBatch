<?php

/*
* This file is part of the h4cc/GuzzleRollingBatch package.
*
* (c) Julius Beckmann <github@h4cc.de>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace h4cc\GuzzleRollingBatch\Tests\RollingBatch;

use Guzzle\Common\Event;
use Guzzle\Http\Client;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\RequestFactory;
use h4cc\GuzzleRollingBatch\RollingBatch;

/**
 * Class RollingbatchTest
 *
 * @author Julius Beckmann <github@h4cc.de>
 */
class RollingbatchTest extends \PHPUnit_Framework_TestCase
{
    protected $host_port = 'http://127.0.0.1:1337/';

    /** @var  RollingBatch */
    private $batch;

    public function setUp()
    {
        $this->batch = new RollingBatch();
    }

    public function testSingleRequest()
    {
        $request = new Request('GET', $this->host_port);
        $this->batch->getRequestQueue()->add($request);

        $this->assertEquals(1, $this->batch->getRequestQueue()->count());
        $this->assertEquals(0, $this->batch->getResponseQueue()->count());

        do {
            $this->batch->execute();
        } while (!$this->batch->isIdle());

        $this->assertEquals(0, $this->batch->getRequestQueue()->count());
        $this->assertEquals(1, $this->batch->getResponseQueue()->count());

        $response = $this->batch->getResponseQueue()->next();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("Hello World", $response->getBody(true));
    }

    public function testDoubleRequest()
    {
        $requestFoo = new Request('GET', $this->host_port . '?content=foo');
        $this->batch->getRequestQueue()->add($requestFoo);

        $requestBar = new Request('GET', $this->host_port . '?content=bar');
        $this->batch->getRequestQueue()->add($requestBar);

        $this->assertEquals(2, $this->batch->getRequestQueue()->count());
        $this->assertEquals(0, $this->batch->getResponseQueue()->count());

        do {
            $this->batch->execute();
        } while (!$this->batch->isIdle());

        $this->assertEquals(0, $this->batch->getRequestQueue()->count());
        $this->assertEquals(2, $this->batch->getResponseQueue()->count());

        // Not sure if we can assume the response order, lets see if it will change...
        $response = $this->batch->getResponseQueue()->next();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("foo", $response->getBody(true));

        $response = $this->batch->getResponseQueue()->next();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("bar", $response->getBody(true));
    }

    public function testMultipleRequests()
    {
        $contents = range('a', 'z');

        foreach ($contents as $content) {
            $request = new Request('GET', $this->host_port . '?content=' . $content);
            $this->batch->getRequestQueue()->add($request);
        }

        $this->assertEquals(count($contents), $this->batch->getRequestQueue()->count());
        $this->assertEquals(0, $this->batch->getResponseQueue()->count());

        do {
            $this->batch->execute();

            $total = $this->batch->getRequestQueue()->count() + $this->batch->countActive()
            + $this->batch->getResponseQueue()->count();
            $this->assertEquals(count($contents), $total);

        } while (!$this->batch->isIdle());

        $this->assertEquals(0, $this->batch->getRequestQueue()->count());
        $this->assertEquals(count($contents), $this->batch->getResponseQueue()->count());

        $responses = array();
        while (!$this->batch->getResponseQueue()->isEmpty()) {
            $responses[] = $this->batch->getResponseQueue()->next()->getBody(true);
        }
        sort($responses);

        $this->assertEquals($contents, $responses);
    }

    /**
     * @expectedException \Guzzle\Http\Exception\CurlException
     */
    public function testTimeoutRequest()
    {
        $request = new Request('GET', $this->host_port . '?content=foo&sleep=3');
        $request->getCurlOptions()->set(CURLOPT_TIMEOUT_MS, 1000); // 1 Second
        $request->getEventDispatcher()
        ->addListener(
            'request.exception',
            function (Event $event) {
                throw $event['exception'];
            }
        );
        $this->batch->getRequestQueue()->add($request);

        do {
            $this->batch->execute();
        } while (!$this->batch->isIdle());
    }

    public function testNumberParallel()
    {
        $this->assertEquals(3, $this->batch->getNumberParallel());

        $this->batch->setNumberParallel(2);

        $this->assertEquals(2, $this->batch->getNumberParallel());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Number has to be > 0.
     */
    public function testNumberParallelInvalid()
    {
        $this->batch->setNumberParallel(-1);
    }
}
