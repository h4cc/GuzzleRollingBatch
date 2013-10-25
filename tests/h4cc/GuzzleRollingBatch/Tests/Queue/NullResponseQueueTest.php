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
use h4cc\GuzzleRollingBatch\Queue\NullResponseQueue;

/**
 * Class ResponseQueueTest
 *
 * @author Julius Beckmann <github@h4cc.de>
 */
class NullResponseQueueTest extends \PHPUnit_Framework_TestCase
{
    public function testResponseQueue()
    {
        $queue = new NullResponseQueue();

        $this->assertEquals(0, $queue->count());
        $this->assertTrue($queue->isEmpty());

        $response = new Response(200);

        $queue->add($response);

        $this->assertEquals(0, $queue->count());
        $this->assertTrue($queue->isEmpty());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No next element.
     */
    public function testRequestQueueException()
    {
        $queue = new NullResponseQueue();
        // Trigger exception
        $queue->next();
    }
}
