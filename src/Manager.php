<?php

namespace Dynamo\Resque;

use Dynamo\Redis\Client as Redis;
use Dynamo\Redis\LuaScript;
use Dynamo\Resque\Jobs\Job;
use Dynamo\Resque\Jobs\JobFactoryInterface;
use Dynamo\Resque\Jobs\JobWrapper;
use Exception;

class Manager
{
    /**
     * Undocumented variable
     *
     * @var \Dynamo\Redis\Client
     */
    protected $redis;

    /**
     * @var string[]
     */
    protected $queues;

    /**
     * @var \Dynamo\Resque\Jobs\JobFactoryInterface
     */
    protected $factory;

    protected $key_prefix;
    protected $settings;

    protected $lua_scripts_loaded = false;

    public function __construct(
        Redis $client,
        array $queues,
        ?JobFactoryInterface $factory = null,
        string $key_prefix = 'resque',
        array $settings = []
    )
    {
        $this->redis = $client;
        $this->queues = $queues;
        $this->factory = $factory;

        $this->settings = (object) array_merge(
            // default settings
            [
                'jobIdCounterKey' => 'next-id',
                'jobsInProgressKey' => 'jobs-in-progress'
            ],
            $settings
        );

        
        $this->key_prefix = $key_prefix;
    }

    /**
     * Gets the Redis client instance being used by this manager
     */
    public function getRedis() : Redis
    {
        return $this->redis;
    }

    public function init()
    {
        $this->redis->loadScript(
            LuaScript::fromFile(
                __DIR__ . '/lua/pushjob.lua',
                6,
                'pushjob'
            ),
            true
        );

        $this->lua_scripts_loaded = true;
    }

    public function push(
        string $job_type,
        string $job_id = '*',
        $args = null,
        int $queue_index = 0
    ) : string
    {
        if(!$this->lua_scripts_loaded){
            $this->init();
        }

        $qcount = count($this->queues);
        $queue = (
            $queue_index < $qcount ?
            $this->queues[$queue_index] :
            $this->queues[$qcount - 1]
        );
        $lua_args = [
            $job_type,
            $queue,
            $job_id,
            "{$this->key_prefix}:{$this->settings->jobIdCounterKey}",
            "{$this->key_prefix}:job:",
            "{$this->key_prefix}:{$this->settings->jobsInProgressKey}",
        ];

        if($args !== null){
            $lua_args[] = (
                is_string($args) ?
                $args :
                json_encode($args)
            );
        }

        $result = $this->redis->pushjob(...$lua_args);

        if(!is_array($result)){
            throw new Exception("Unexpected push reply. Expected: array, got: " . json_encode($result));
        }

        $error = (int) $result[0];
        if($error !== 0){
            switch($error){
                case 1:
                    $message = "Job with same ID already exists";
                    break;
                default:
                    $message = "Unknown error code {$error}";
            }
            throw new Exception("Push job error: {$message}", $error);
        }

        return $result[1];
    }

    public function pop(int $timeout) : ?Job
    {
        if(!$this->factory){
            throw new Exception("Please pass a JobFactory object to the Manager constructor, so you can pop and parse jobs");
        }

        $blpop_args = array_merge($this->queues, [ $timeout ]);
        $result = $this->redis->blPop(...$blpop_args);
        if(is_array($result) && count($result) >= 2){
            $queue = $result[0];
            $payload = $result[1];
            return $this->factory->decode($payload, $queue);
        }

        return null;
    }

    public function updateJob(Job $job, array $fields)
    {
        $this->redis->hMSet("{$this->key_prefix}:job:{$job->id}", $fields);
    }
}