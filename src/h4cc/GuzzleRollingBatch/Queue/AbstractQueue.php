<?php

/*
 * This file is part of the h4cc/GuzzleRollingBatch package.
 *
 * (c) Julius Beckmann <github@h4cc.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace h4cc\GuzzleRollingBatch\Queue;

/**
 * Class AbstractQueue
 *
 * @author Julius Beckmann <github@h4cc.de>
 */
abstract class AbstractQueue
{
    /**
     * @var \SplQueue<RequestInterface>
     */
    protected $queue;

    public function __construct()
    {
        $this->clear();
    }

    /**
     * Returns next element from queue.
     *
     * @return mixed
     * @throws \RuntimeException
     */
    public function next()
    {
        try {
            $element = $this->queue->dequeue();
            return $element;
        } catch (\RuntimeException $exception) {
            throw new \RuntimeException("No next element.");
        }
    }

    /**
     * Queue length.
     *
     * @return integer
     */
    public function count()
    {
        return $this->queue->count();
    }

    /**
     * Returns true if queue is empty.
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return $this->queue->isEmpty();
    }

    /**
     * Removes all items from queue.
     */
    public function clear()
    {
        $this->queue = new \SplQueue();
    }
}
