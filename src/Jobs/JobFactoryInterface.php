<?php

namespace Dynamo\Resque\Jobs;

interface JobFactoryInterface
{
    public function create(string $type, $args = null, $id = null) : Job;
    public function encode(Job $job) : string;
    public function decode(string $payload, string $queue) : Job;
}
