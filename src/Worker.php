<?php

namespace Dynamo\Resque;

use Dynamo\Redis\Client as Redis;
use Dynamo\Resque\Jobs\Job;
use Dynamo\Resque\Jobs\JobFactoryInterface;
use Psr\Log\LoggerInterface;

class Worker extends Process
{
    /**
     * Worker reserved name
     *
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $queues = [];

    /**
     * @var \Dynamo\Resque\Manager
     */
    protected $manager;

    /**
     * Redis client
     *
     * @var \Dynamo\Redis\Client
     */
    protected $redis;

    /**
     * @var \Dynamo\Resque\Jobs\JobFactoryInterface
     */
    protected $jobFactory;
    
    /**
     * Whether to fork when a new job is poped, or not
     *
     * @var boolean
     */
    protected $fork = true;

    /**
     * If $fork == true, indicates the max number of forked processes (running jobs)
     * that can be running before poping new jobs.
     *
     * @var integer
     */
    protected $max_concurrency = -1;

    protected $redis_keys_prefix = "php-resque";

    
    protected $blpop_timeout = 10;

    protected $running_jobs = [];

    public function __construct(
        Manager $manager,
        JobFactoryInterface $jobFactory,
        array $queues,
        ?LoggerInterface $logger = null
    )
    {
        $this->redis = $manager->getRedis();
        $this->jobFactory = $jobFactory;
        $this->queues = $queues;
        parent::__construct($logger);
    }

    protected function getWorkerNames() : array
    {
        return [
            'Alfa', 'Bravo', 'Charlie', 'Delta', 'Eco', 'Foxtrot',
            'Golf', 'Hotel', 'India', 'Juliet', 'Kilo', 'Lima',
            'Mike', 'November', 'Oscar', 'Papa', 'Quebec',
            'Romeo', 'Sierra', 'Tango', 'Uniform', 'Victor',
            'Whiskey', 'X-ray', 'Yankee', 'Zulu'
        ];
    }

    protected function reserveName() : string
    {
        $names = $this->getWorkerNames();
        $count = count($names);

        $id = $this->redis->incr("{$this->redis_keys_prefix}:next-worker-id");

        return $names[$id % $count] . '-' . ceil($id / $count);
    }

    protected function registerWorker()
    {
        $this->redis->hSet(
            "{$this->redis_keys_prefix}:workers",
            $this->name,
            json_encode($this->getState())
        );
    }

    protected function updateJob(Job $job)
    {

    }

    protected function init()
    {
        $this->name = $this->reserveName();
        $this->registerWorker();
    }

    protected function run()
    {
        while(!$this->shutdown){
            $job = $this->pop();
            if($job){
                $this->redis->updateJob();
                if($this->logger){
                    $this->logger->debug("Job poped from '{$job->getQueue()}'! Type: ".get_class($job));
                }
                if($this->fork){
                    $pid = $this->fork();
                    if($pid > 0){
                        $job->setPid($pid);
                        $this->running_jobs[] = $job->getState();
                        $this->updateState();
                    } else if($pid === 0){
                        //forked process context
                    }
                } else {
                    $job->perform();
                }
                

            } else {

            }
        }
    }

    protected function pop() : ?Job
    {
        $queues = array_merge(
            [ "{$this->redis_keys_prefix}:{$this->name}:queue" ],
            $this->queues
        );

        if($this->logger){
            $this->logger->debug("Blocking pop from: ".implode(', ', $queues));
        }

        $blpop_args = array_merge(
            $queues,
            [ $this->blpop_timeout ]
        );

        $result = $this->redis->blpop(...$blpop_args);

        if(!is_array($result) || count($result) != 2){
            return null;
        }

        $queue = $result[0];
        $payload = json_decode($result[1], true);

        if(!$payload || !isset($payload['type'])){
            return null;
        }

        return $this->jobFactory->parse(
            $payload['type'],
            $payload['args'] ?? null
        );
    }

    protected function updateState()
    {
        //$this->
    }

    protected function getState()
    {
        return [
            'running_jobs' => count($this->forked_children),
        ];
    }

}