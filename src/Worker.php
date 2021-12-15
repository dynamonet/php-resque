<?php

namespace Dynamo\Resque;

use Dynamo\Redis\Client as Redis;
use Dynamo\Resque\Jobs\Job;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

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
     * This container is passed to every job through the "setup" method, before the job starts.
     *
     * @var \Psr\Container\ContainerInterface
     */
    protected $container;

    /**
     * Redis client
     *
     * @var \Dynamo\Redis\Client
     */
    protected $redis;
    
    /**
     * Whether to fork when a new job is poped, or not
     *
     * @var boolean
     */
    protected $fork = false;

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

    /**
     * Undocumented function
     *
     * @param Manager $manager
     * @param Logger|null $logger
     * @param ContainerInterface|null $container
     */
    public function __construct(
        Manager $manager,
        ?LoggerInterface $logger = null,
        ?ContainerInterface $container = null
    )
    {
        $this->manager = $manager;
        $this->container = $container;
        $this->redis = $manager->getRedis();

        parent::__construct($logger);
    }

    /**
     * Sets the forking behaviour. If TRUE, forking will be enabled, and the worker will fork
     * itself to run every poped job. FALSE: the worker won't fork, and will process the jobs
     * synchrounously (the worker won't be able to do anything until the job finishes)
     *
     * @param boolean $enabled
     * @return void
     */
    public function setFork(bool $enabled)
    {
        $this->fork = $enabled;
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

    protected function unregisterWorker()
    {
        //TODO
    }

    protected function updateJob(Job $job)
    {

    }

    protected function init()
    {
        parent::init();
        $this->name = $this->reserveName();
        $this->registerWorker();
    }

    protected function run()
    {
        while(!$this->shutdown){
            $this->logger->debug("Waiting for a job. Timeout: {$this->blpop_timeout}s");
            $job = $this->manager->pop($this->blpop_timeout);
            if($job){
                $this->manager->updateJob($job, [
                    'worker' => $this->name,
                ]);
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
                        $this->processJob($job);
                        exit;
                    }
                } else {
                    $this->processJob($job);
                }
                

            } else {

            }
        }

        $this->logger->info("Worker '{$this->name}' shutting down");

        $this->unregisterWorker();
    }

    protected function processJob(Job $job)
    {
        try {
            $job->setup($this->container);
            $job->setLogger($this->logger);
            $job->perform();
        } catch(Throwable $error) {
            $this->logger->error(sprintf("Failed to process '%s' job: %s", get_class($job), $error->getMessage()));

        }
    }

    /*protected function pop() : ?Job
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
    }*/

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