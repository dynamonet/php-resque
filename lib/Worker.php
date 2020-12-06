<?php

namespace Dynamo\Resque;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Dynamo\Resque\Job\FactoryInterface;
use CredisException;
use Dynamo\Resque\Job\PID;
use Dynamo\Resque\Job\Status as JobStatus;
use Dynamo\Resque\Job\DirtyExitException;
use Throwable;

/**
 * Resque worker that handles checking queues for jobs, fetching them
 * off the queues, running them and handling the result.
 *
 * @package		Resque/Worker
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Worker
{
	/**
	 * @var string Prefix for the process name
	 */
	private static $processPrefix = 'resque';

	/**
	* @var LoggerInterface Logging object that impliments the PSR-3 LoggerInterface
	*/
	protected $logger;

	/**
	* @var FactoryInterface Job factory
	*/
	protected $jobFactory;

	/**
	 * @var bool Whether this worker is running in a forked child process.
	 */
	public $hasParent = false;

	/**
	 * @var array Array of all associated queues for this worker.
	 */
	private $queues = array();

	/**
	 * @var string The hostname of this worker.
	 */
	private $hostname;

	/**
	 * @var boolean True if on the next iteration, the worker should shutdown.
	 */
	private $shutdown = false;

	/**
	 * @var boolean True if this worker is paused.
	 */
	private $paused = false;

	/**
	 * @var string String identifying this worker.
	 */
	private $id = null;

	/**
	 * @var Job Current job, if any, being processed by this worker.
	 */
	private $currentJob = null;

	/**
	 * @var int Process ID of child worker processes.
	 */
	private $child = null;

    /**
     * Instantiate a new worker, given a list of queues that it should be working
     * on. The list of queues should be supplied in the priority that they should
     * be checked for jobs (first come, first served)
     *
     * Passing a single '*' allows the worker to work on all queues in alphabetical
     * order. You can easily add new queues dynamically and have them worked on using
     * this method.
     *
     * @param string|array $queues String with a single queue name, array with multiple.
     */
    public function __construct(
		$queues,
		LoggerInterface $logger = null,
		FactoryInterface $jobFactory = null
	)
    {
		$this->logger = $logger;
		$this->jobFactory = $jobFactory;

        if(!is_array($queues)) {
            $queues = array($queues);
        }

        $this->queues = $queues;
        $this->hostname = php_uname('n');

        //$this->id = $this->getId();
	}
	
	/**
	 * Undocumented function
	 *
	 * @return string
	 */
	protected function getId()
	{
		//return $this->hostname . ':'.getmypid() . ':' . implode(',', $this->queues);
		if($this->id == null){
			$id = Resque::redis()->incr('next-worker-id');
			$names = $this->getNames();
			$n = count($names);
			$this->id = $names[$id % $n] . '-' . ceil($id / $n);
		}

		return $this->id;
	}

	protected function getNames()
	{
		return [
			'Alfa',
			'Bravo',
			'Charlie',
			'Delta',
			'Echo',
			'Foxtrot',
			'Golf',
			'Hotel'
		];
	}

	/**
	 * Set the process prefix of the workers to the given prefix string.
	 * @param string $prefix The new process prefix
	 */
	public static function setProcessPrefix($prefix)
	{
		self::$processPrefix = $prefix;
	}

	/**
	 * Return all workers known to Resque as instantiated instances.
	 * @return array
	 */
	public static function all()
	{
		$workers = Resque::redis()->smembers('workers');
		if(!is_array($workers)) {
			$workers = array();
		}

		$instances = array();
		foreach($workers as $workerId) {
			$instances[] = self::find($workerId);
		}
		return $instances;
	}

	/**
	 * Given a worker ID, check if it is registered/valid.
	 *
	 * @param string $workerId ID of the worker.
	 * @return boolean True if the worker exists, false if not.
	 */
	public static function exists($workerId)
	{
		return (bool)Resque::redis()->sismember('workers', $workerId);
	}

	/**
	 * Given a worker ID, find it and return an instantiated worker class for it.
	 *
	 * @param string $workerId The ID of the worker.
	 * @return Worker Instance of the worker. False if the worker does not exist.
	 */
	public static function find($workerId)
	{
		if(!self::exists($workerId) || false === strpos($workerId, ":")) {
			return false;
		}

		list($hostname, $pid, $queues) = explode(':', $workerId, 3);
		$queues = explode(',', $queues);
		$worker = new self($queues);
		$worker->setId($workerId);
		return $worker;
	}

	/**
	 * Set the ID of this worker to a given ID string.
	 *
	 * @param string $workerId ID for the worker.
	 */
	public function setId($workerId)
	{
		$this->id = $workerId;
	}

	/**
	 * The primary loop for a worker which when called on an instance starts
	 * the worker's life cycle.
	 *
	 * Queues are checked every $interval (seconds) for new jobs.
	 *
	 * @param int $interval How often to check for new jobs across the queues.
	 */
	public function work($interval = Resque::DEFAULT_INTERVAL, $blocking = true)
	{
		$this->updateProcLine('Starting');
		$this->startup();

        if(function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

		while(true) {
			if($this->shutdown) {
				break;
			}

			// is redis still alive?
			//TODO: refactor this
			/*try {
			    if (Resque::redis()->ping() === false) {
			        throw new CredisException('redis ping() failed');
			    }
			} catch (CredisException $e) {
			    $this->logger->error('redis went away. trying to reconnect');
			    Resque::$redis = null;
			    usleep($interval * 1000000);
			    continue;
			}*/

			// Attempt to find and reserve a job
			$job = false;
			if(!$this->paused) {
				if($blocking === true) {
					$this->logger->info("Starting blocking with timeout of {$interval}");
					//$this->updateProcLine('Waiting with blocking timeout ' . $interval);
				} else {
					//$this->updateProcLine('Waiting with interval ' . $interval);
				}

				$job = $this->reserve($interval);
			}

			if(!$job) {
				// For an interval of 0, break now - helps with unit testing etc
				if($interval == 0) {
					break;
				}

				if($blocking === false)
				{
					// If no job was found, we sleep for $interval before continuing and checking again
					$this->logger->info('Sleeping for {interval}', array('interval' => $interval));
					if($this->paused) {
						$this->updateProcLine('Paused');
					}
					else {
						$this->updateProcLine('Waiting');
					}

					usleep($interval * 1000000);
				}

				continue;
			}

			$this->logger->notice('Starting work on {job}', array('job' => $job));
			Event::trigger('beforeFork', $job);
			$this->workingOn($job);

			$this->child = Resque::fork();

			// Forked and we're the child. Or PCNTL is not installed. Run the job.
			if ($this->child === 0 || $this->child === false || $this->child === -1) {
				$status = 'Processing ' . $job->queue . ' since ' . strftime('%F %T');
				//$this->updateProcLine($status);
				$this->logger->info($status);

				if(!empty($job->payload['id'])) {
					PID::create($job->payload['id']);
				}

				$this->perform($job);

				if(!empty($job->payload['id'])) {
					PID::del($job->payload['id']);
				}

				if ($this->child === 0) {
					exit(0);
				}
			}

			if($this->child > 0) {
				// Parent process, sit and wait
				$status = 'Forked ' . $this->child . ' at ' . strftime('%F %T');
				$this->updateProcLine($status);
				$this->logger->info($status);

				// Wait until the child process finishes before continuing
				while (pcntl_wait($status, WNOHANG) === 0) {
					if(function_exists('pcntl_signal_dispatch')) {
			            pcntl_signal_dispatch();
			        }

					// Pause for a half a second to conserve system resources
					usleep(500000);
				}

				if (pcntl_wifexited($status) !== true) {
					$job->fail(new DirtyExitException('Job exited abnormally'));
				} elseif (($exitStatus = pcntl_wexitstatus($status)) !== 0) {
					$job->fail(new DirtyExitException(
						'Job exited with exit code ' . $exitStatus
					));
				}
				else
				{
					if (in_array($job->getStatus(), array(JobStatus::STATUS_WAITING, JobStatus::STATUS_RUNNING)))
					{
						$job->updateStatus(JobStatus::STATUS_COMPLETE);
						$this->logger->info('done ' . $job);
					}
				}
			}

			$this->child = null;
			$this->doneWorking();
		}

		$this->unregisterWorker();
	}

	/**
	 * Process a single job.
	 *
	 * @param Job $job The job to be processed.
	 */
	public function perform(Job $job)
	{
		$result = null;
		try {
			Event::trigger('afterFork', $job);
			$result = $job->perform();
		}
		catch(Throwable $e) {
			$this->logger->critical('{job} has failed {exception}', array('job' => $job, 'exception' => $e));
			$job->fail($e);
			return;
		}

		$job->updateStatus(JobStatus::STATUS_COMPLETE, $result);
		$this->logger->notice('{job} has finished', ['job' => $job]);
	}

	/**
	 * @param  int             $timeout
	 * @return Job|bool        Instance of Job if a job is found, false if not.
	 */
	public function reserve($timeout = null)
	{
		if ($this->hasParent && !posix_kill(posix_getppid(), 0))
		{
			$this->shutdown();
			return false;
		}

		$queues = $this->queues();
		if(!is_array($queues)) {
			return false;
		}

		if(empty($queues)){
			$this->logger->info('No queue was found, sleeping for {interval}', array('interval' => $timeout));
			usleep($timeout * 1000000);
			return false;
		}
		$raw = Resque::blpop($queues, $timeout);
		if($raw){
			try{
				$this->logger->debug("Found job on {$raw->queue}");
				$job = $this->jobFactory->parse($raw['payload'], $raw['queue']);
			} catch(Throwable $parseErr){
				$this->logger->error("Failed to parse job");
			}
			
		}

		if($job) {
			return $job;
		}
		

		return false;
	}

	/**
	 * Return an array containing all of the queues that this worker should use
	 * when searching for jobs.
	 *
	 * If * is found in the list of queues, every queue will be searched in
	 * alphabetic order. (@see $fetch)
	 *
	 * @param boolean $fetch If true, and the queue is set to *, will fetch
	 * all queue names from redis.
	 * @return array Array of associated queues.
	 */
	public function queues($fetch = true)
	{
		if(!in_array('*', $this->queues) || $fetch == false) {
			return $this->queues;
		}

		$queues = Resque::queues();
		sort($queues);
		return $queues;
	}

	/**
	 * Perform necessary actions to start a worker.
	 */
	private function startup()
	{
		$this->registerSigHandlers();
		$this->pruneDeadWorkers();
		Event::trigger('beforeFirstFork', $this);
		$this->registerWorker();
	}

	/**
	 * On supported systems (with the PECL proctitle module installed), update
	 * the name of the currently running process to indicate the current state
	 * of a worker.
	 *
	 * @param string $status The updated process title.
	 */
	private function updateProcLine($status)
	{
		$processTitle = static::$processPrefix . '-' . Resque::VERSION . ' (' . implode(',', $this->queues) . '): ' . $status;
		if(function_exists('cli_set_process_title') && PHP_OS !== 'Darwin') {
			cli_set_process_title($processTitle);
		}
		else if(function_exists('setproctitle')) {
			setproctitle($processTitle);
		}
	}

	/**
	 * Register signal handlers that a worker should respond to.
	 *
	 * TERM: Shutdown immediately and stop processing jobs.
	 * INT: Shutdown immediately and stop processing jobs.
	 * QUIT: Shutdown after the current job finishes processing.
	 * USR1: Kill the forked child immediately and continue processing jobs.
	 */
	private function registerSigHandlers()
	{
		if(!function_exists('pcntl_signal')) {
			return;
		}

		pcntl_async_signals(true);
		pcntl_signal(SIGTERM, array($this, 'shutDownNow'));
		pcntl_signal(SIGINT, array($this, 'shutDownNow'));
		pcntl_signal(SIGQUIT, array($this, 'shutdown'));
		pcntl_signal(SIGUSR1, array($this, 'killChild'));
		pcntl_signal(SIGUSR2, array($this, 'pauseProcessing'));
		pcntl_signal(SIGCONT, array($this, 'unPauseProcessing'));
		$this->logger->debug('Registered signals');
	}

	/**
	 * Signal handler callback for USR2, pauses processing of new jobs.
	 */
	public function pauseProcessing()
	{
		$this->logger->notice('USR2 received; pausing job processing');
		$this->paused = true;
	}

	/**
	 * Signal handler callback for CONT, resumes worker allowing it to pick
	 * up new jobs.
	 */
	public function unPauseProcessing()
	{
		$this->logger->notice('CONT received; resuming job processing');
		$this->paused = false;
	}

	/**
	 * Schedule a worker for shutdown. Will finish processing the current job
	 * and when the timeout interval is reached, the worker will shut down.
	 */
	public function shutdown()
	{
		$this->shutdown = true;
		$this->logger->notice('Shutting down');
	}

	/**
	 * Force an immediate shutdown of the worker, killing any child jobs
	 * currently running.
	 */
	public function shutdownNow()
	{
		$this->shutdown();
		$this->killChild();
	}

	/**
	 * @return int Child process PID.
	 */
	public function getChildPID() {
		return $this->child;
	}

	/**
	 * Kill a forked child job immediately. The job it is processing will not
	 * be completed.
	 */
	public function killChild()
	{
		if(!$this->child) {
			$this->logger->debug('No child to kill.');
			return;
		}

		$this->logger->info('Killing child at {child}', array('child' => $this->child));
		if(exec('ps -o pid,s -p ' . $this->child, $output, $returnCode) && $returnCode != 1) {
			$this->logger->debug('Child {child} found, killing.', array('child' => $this->child));
			posix_kill($this->child, SIGKILL);
			$this->child = null;
		}
		else {
			$this->logger->info('Child {child} not found, restarting.', array('child' => $this->child));
			$this->shutdown();
		}
	}

	/**
	 * Look for any workers which should be running on this server and if
	 * they're not, remove them from Redis.
	 *
	 * This is a form of garbage collection to handle cases where the
	 * server may have been killed and the Resque workers did not die gracefully
	 * and therefore leave state information in Redis.
	 */
	public function pruneDeadWorkers()
	{
		$workerPids = $this->workerPids();
		$workers = self::all();
		foreach($workers as $worker) {
			if (is_object($worker)) {
				list($host, $pid, $queues) = explode(':', (string)$worker, 3);
				if($host != $this->hostname || in_array($pid, $workerPids) || $pid == getmypid()) {
					continue;
				}
				$this->logger->info('Pruning dead worker: {worker}', array('worker' => (string)$worker));
				$worker->unregisterWorker();
			}
		}
	}

	/**
	 * Return an array of process IDs for all of the Resque workers currently
	 * running on this machine.
	 *
	 * @return array Array of Resque worker process IDs.
	 */
	public function workerPids()
	{
		$pids = array();
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			exec('WMIC path win32_process get Processid,Commandline | findstr resque | findstr /V findstr', $cmdOutput);
			foreach($cmdOutput as $line) {
				$line = preg_replace('/\s+/m', ' ', $line);
				list(,,$pids[]) = explode(' ', trim($line), 3);
			}
		}
		else {
			exec('ps -A -o pid,args | grep [r]esque', $cmdOutput);
			foreach($cmdOutput as $line) {
				list($pids[],) = explode(' ', trim($line), 2);
			}
		}
		return $pids;
	}

	/**
	 * Register this worker in Redis.
	 */
	public function registerWorker()
	{
		$id = $this->getId();
		Resque::redis()->sadd('workers', (string) $id);
		Resque::redis()->set('worker:' . (string)$this . ':started', date('c'));
	}

	/**
	 * Unregister this worker in Redis. (shutdown etc)
	 */
	public function unregisterWorker()
	{
		if(is_object($this->currentJob)) {
			$this->currentJob->fail(new DirtyExitException);
		}

		$id = (string)$this;
		//TODO: pipeline this
		Resque::redis()->srem('workers', $id);
		Resque::redis()->del('worker:' . $id);
		Resque::redis()->del('worker:' . $id . ':started');
		Stat::clear('processed:' . $id);
		Stat::clear('failed:' . $id);
	}

	/**
	 * Tell Redis which job we're currently working on.
	 *
	 * @param object $job Job instance containing the job we're working on.
	 */
	public function workingOn(Job $job)
	{
		$job->worker = $this;
		$this->currentJob = $job;
		$job->updateStatus(JobStatus::STATUS_RUNNING);
		$data = json_encode([
			'queue' => $job->queue,
			'run_at' => date('c'),
			'payload' => $job->payload
		]);
		Resque::redis()->sadd("worker:{$this->id}:jobs", $data);
	}

	/**
	 * Notify Redis that we've finished working on a job, clearing the working
	 * state and incrementing the job stats.
	 */
	public function doneWorking()
	{
		$this->currentJob = null;
		Resque::redis()->pipeline()
			->hIncrBy('stats','processed',1)
			->hIncrBy("worker:{$this->getId()}:stats",'processed',1)
			->exec();
	}

	/**
	 * Generate a string representation of this worker.
	 *
	 * @return string String identifier for this worker instance.
	 */
	public function __toString()
	{
		return $this->id;
	}

	/**
	 * Return an object describing the job this worker is currently working on.
	 *
	 * @return object Object with details of current job.
	 */
	public function job()
	{
		$job = Resque::redis()->get('worker:' . $this);
		if(!$job) {
			return array();
		}
		else {
			return json_decode($job, true);
		}
	}

	/**
	 * Get a statistic belonging to this worker.
	 *
	 * @param string $stat Statistic to fetch.
	 * @return int Statistic value.
	 */
	public function getStat($stat)
	{
		return Resque_Stat::get($stat . ':' . $this);
	}

	/**
	 * Inject the logging object into the worker
	 *
	 * @param Psr\Log\LoggerInterface $logger
	 */
	public function setLogger(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}
}
