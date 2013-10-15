<?php

/*
 * This file is part of the h4cc/GuzzleRollingBatch package.
 *
 * (c) Julius Beckmann <github@h4cc.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Simple http server using React.
 */

$port = (isset($argv[1])) ? (int)$argv[1] : 1337;

require_once(__DIR__ . '/../vendor/autoload.php');

$app = function (\React\Http\Request $request, \React\Http\Response $response) {
    $get = $request->getQuery();
    $content = (isset($get['content'])) ? $get['content'] : "Hello World";
    $status = (isset($get['status'])) ? $get['status'] : 200;
    $contentType = (isset($get['content_type'])) ? $get['content_type'] : 'text/plain';

    if (isset($get['sleep'])) {
        sleep($get['sleep']);
    }

    // Output
    $response->writeHead($status, array('Content-Type' => $contentType));
    $response->end($content);
};

$loop = \React\EventLoop\Factory::create();
$socket = new \React\Socket\Server($loop);
$http = new \React\Http\Server($socket, $loop);

$http->on('request', $app);
$socket->listen($port);

echo getmypid();

$loop->run();
