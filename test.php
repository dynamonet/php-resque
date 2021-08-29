<?php

use Dynamo\Redis\Client;
use Dynamo\Resque\Jobs\DefaultJobFactory;
use Dynamo\Resque\Worker;

require __DIR__ . '/vendor/autoload.php';

$worker = new Worker(
    new Client(['host' => 'host.docker.internal']),
    new DefaultJobFactory([]),
    [ 'resque-test' ],
    new \Katzgrau\KLogger\Logger('php://stdout')
);

$worker->start();