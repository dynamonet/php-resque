<?php

namespace Dynamo\Resque\Jobs;

class JobWrapper {
    public $type;
    public $queue;
    public $id;
    public $args;

    public function __construct(
        string $type,
        string $queue,
        string $id,
        $args = null
    )
    {
        $this->type = $type;
        $this->queue = $queue;
        $this->id = $id;
        $this->args = $args;
    }
}
