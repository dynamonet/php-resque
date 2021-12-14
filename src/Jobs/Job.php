<?php

namespace Dynamo\Resque\Jobs;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class Job
{
    const QUEUED = 1;
    const RUNNING = 2;
    const SUCCESS = 3;
    
    public $id;
    protected $queue;
    protected $pid;
    protected $start;
    protected $args;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    
    public function __construct(
        $args = null,
        string $id = null,
        string $queue = null
    )
    {
        $this->queue = $queue;
        if($args){
            $this->parseArgs($args);
        }
    }

    public function getArgs()
    {
        return $this->args;
    }

    public function getQueue()
    {
        return $this->queue;
    }

    public function setup(ContainerInterface $container)
    {
        $this->logger = $container->get(LoggerInterface::class);
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
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