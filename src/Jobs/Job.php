<?php

namespace Dynamo\Resque\Jobs;

abstract class Job
{
    const QUEUED = 1;
    const RUNNING = 2;
    const SUCCESS = 3;
    
    protected $queue;
    protected $pid;
    protected $start;
    protected $args;
    
    public function __construct(
        string $queue = null,
        $args = null
    )
    {
        $this->queue = $queue;
        if($args){
            $this->parseArgs($args);
        }
    }

    abstract public function perform();

    public function parseArgs($args)
    {
        $this->args = (
            is_string($args) ?
            json_decode($args) :
            $args
        );
    }

    /**
     * Sets the process_id of the forked process on which this job is performing
     *
     * @param integer $pid
     * @return void
     */
    public function setPid(int $pid){
        $this->pid = $pid;
    }

    public function getState() : array
    {
        return [
            'id' => $this->id,
            'started'

        ];
    }
}