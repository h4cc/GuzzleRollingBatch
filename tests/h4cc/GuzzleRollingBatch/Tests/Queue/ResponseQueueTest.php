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

use Guzzle\Http\Message\Response;
use h4cc\GuzzleRollingBatch\Queue\ResponseQueue;

/**
 * Class ResponseQueueTest
 *
 * @author Julius Beckmann <github@h4cc.de>
 */
class ResponseQueueTest extends \PHPUnit_Framework_TestCase
{
    public function testResponseQueue()
    {
        $queue = new ResponseQueue();

        $this->assertEquals(0, $queue->count());
        $this->assertTrue($queue->isEmpty());

        $response = new Response(200);

        $queue->add($response);

        $this->assertEquals(1, $queue->count());
        $this->assertFalse($queue->isEmpty());

        $queue->add($response);

        $this->assertEquals(2, $queue->count());
        $this->assertFalse($queue->isEmpty());

        $this->assertEquals($response, $queue->next());
        $this->assertEquals($response, $queue->next());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No next element.
     */
    public function testRequestQueueException()
    {
        $queue = new ResponseQueue();
        // Trigger exception
        $queue->next();
    }
}
