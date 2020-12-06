<?php

namespace Dynamo\Resque\Job;

use Dynamo\Resque\JobInterface;
use Dynamo\Resque\ResqueException;

class DefaultFactory implements FactoryInterface
{

    /**
     * @param $className
     * @param array $args
     * @param $queue
     * @return JobInterface
     * @throws \ResqueException
     */
    public function create($className, $args, $queue) : JobInterface
    {
        if (!class_exists($className)) {
            throw new ResqueException(
                'Could not find job class ' . $className . '.'
            );
        }

        if (!method_exists($className, 'perform')) {
            throw new ResqueException(
                'Job class ' . $className . ' does not contain a perform method.'
            );
        }

        $instance = new $className;
        $instance->args = $args;
        $instance->queue = $queue;

        return $instance;
    }

    public function parse(string $rawPayload, string $queue): JobInterface
    {
        $obj = json_decode($rawPayload);

        if(!$obj){
            throw new ResqueException("Failed to parse job: invalid JSON: $rawPayload");
        }

        return $this->create($obj->class, $obj->args, $queue);  
    }
}
