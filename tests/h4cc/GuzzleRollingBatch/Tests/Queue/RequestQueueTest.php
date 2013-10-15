<?php

/*
 * This file is part of the h4cc/GuzzleRollingBatch package.
 *
 * (c) Julius Beckmann <github@h4cc.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace h4cc\GuzzleRollingBatch\Tests\Queue;

use Guzzle\Http\Message\Request;
use h4cc\GuzzleRollingBatch\Queue\RequestQueue;

/**
 * Class RequestQueueTest
 *
 * @author Julius Beckmann <github@h4cc.de>
 */
class RequestQueueTest extends \PHPUnit_Framework_TestCase
{
    public function testRequestQueue()
    {
        $queue = new RequestQueue();

        $this->assertEquals(0, $queue->count());
        $this->assertTrue($queue->isEmpty());

        $request = new Request('GET', '/');

        $queue->add($request);

        $this->assertEquals(1, $queue->count());
        $this->assertFalse($queue->isEmpty());

        $queue->add($request);

        $this->assertEquals(2, $queue->count());
        $this->assertFalse($queue->isEmpty());

        $this->assertEquals($request, $queue->next());
        $this->assertEquals($request, $queue->next());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No next element.
     */
    public function testRequestQueueException()
    {
        $queue = new RequestQueue();
        // Trigger exception
        $queue->next();
    }
}
