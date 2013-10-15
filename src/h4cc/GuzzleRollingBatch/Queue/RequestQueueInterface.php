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

use Guzzle\Http\Message\RequestInterface;

/**
 * Class RequestQueueInterface
 *
 * @author Julius Beckmann <github@h4cc.de>
 */
interface RequestQueueInterface
{
    /**
     * Adds a Request.
     */
    public function add(RequestInterface $request);

    /**
     * Returns next Request.
     *
     * @return RequestInterface
     */
    public function next();

    /**
     * Queue length.
     *
     * @return integer
     */
    public function count();

    /**
     * Returns true if queue is empty.
     *
     * @return boolean
     */
    public function isEmpty();

    /**
     * Removes all items from queue.
     */
    public function clear();
}
