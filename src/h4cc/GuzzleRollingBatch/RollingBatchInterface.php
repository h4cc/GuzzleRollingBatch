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

use h4cc\GuzzleRollingBatch\Queue\RequestQueueInterface;
use h4cc\GuzzleRollingBatch\Queue\ResponseQueueInterface;

/**
 * Class RollingBatchInterface
 *
 * @author Julius Beckmann <github@h4cc.de>
 */
interface RollingBatchInterface
{
    /**
     * @return RequestQueueInterface
     */
    public function getRequestQueue();

    /**
     * @return ResponseQueueInterface
     */
    public function getResponseQueue();

    /**
     * Executes active handles.
     *
     * @return bool Should be called again
     */
    public function execute();

    /**
     * Returns number of currently active requests.
     *
     * @return int
     */
    public function countActive();

    /**
     * Returns true, if batch has processed all requests and no active ones are outstanding.
     *
     * @return bool
     */
    public function isIdle();
}
