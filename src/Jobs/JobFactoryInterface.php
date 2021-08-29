<?php

namespace Dynamo\Resque\Jobs;

interface JobFactoryInterface
{
    public function parse(string $queue, $payload) : Job;
    public function fromType(string $type, $args = null) : Job;
}
