<?php

namespace Dynamo\Resque;

use RuntimeException;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class Process
{
	protected $pid;
	protected $shutdown;
	protected $paused;

	protected $forked_children = []; 

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

    #region to implement by child classes
    protected abstract function run();
    #endregion

    public function __construct(?LoggerInterface $logger = null)
	{
		$this->logger = $logger;
	}

    public function getPid()
    {
        return $this->pid;
	}
	
	protected function init()
	{
		$this->registerSigHandlers();
	}

	/**
	 * Starts the process. Blocks until an OS signal is received
	 *
	 * @return void
	 */
    public function start()
    {
		try{
			$this->init();
			$this->run();
		} catch(Throwable $err) {
			if($this->logger){
				$this->logger->error(
					sprintf(
						"EXCEPTION IN PROCESS '%s': %s",
						get_class($this),
						$err->getMessage()
					)
				);
			}
		}
	}

	protected function fork()
	{
		if(!function_exists('pcntl_fork')) {
			throw new RuntimeException('Unable to fork. Please install and enable pcntl extension');
		}

		$pid = pcntl_fork();
		if($pid === -1) {
			throw new RuntimeException('Unable to fork child worker.');
		}

		if($pid > 0 && !in_array($pid, $this->forked_children)){
			$this->forked_children[] = $pid;
		}

		return $pid;
	}

	public function isRunning()
	{
		if($this->pid <= 0){
			//called from child
			return true;
		}

		$res = pcntl_waitpid($this->pid, $status, WNOHANG);
       
        // If the process has already exited
        return ( $res == 0 );
	}
	
	/**
	 * Block and wait for the process to end
	 */
	public function wait()
	{
		$status = -1;

		if($this->pid > 0){
			\pcntl_waitpid($this->pid, $status);

		}

		return $status;
	}
    
    /**
	 * On supported systems (with the PECL proctitle module installed), update
	 * the name of the currently running process to indicate the current state
	 * of a worker.
	 *
	 * @param string $status The updated process title.
	 */
	protected function updateProcLine($status)
	{
		$processTitle = 'dynamo-worker: ' . $status;
		if(function_exists('cli_set_process_title') && PHP_OS !== 'Darwin') {
			cli_set_process_title($processTitle);
		} else if(function_exists('setproctitle')) {
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

		pcntl_signal(SIGTERM, array($this, 'shutdown'));
		//pcntl_signal(SIGKILL, array($this, 'shutdown'));
		pcntl_signal(SIGINT, array($this, 'shutdown'));
		pcntl_signal(SIGQUIT, array($this, 'shutdown'));
		//pcntl_signal(SIGUSR1, array($this, 'killChild'));
		pcntl_signal(SIGUSR2, array($this, 'pauseProcessing'));
		pcntl_signal(SIGCONT, array($this, 'unPauseProcessing'));
		$this->logger->debug('OS signals successfuly registered');
	}

	/**
	 * Signal handler callback for USR2, pauses processing of new jobs.
	 */
	public function pauseProcessing()
	{
		$this->paused = true;
	}

	/**
	 * Signal handler callback for CONT, resumes worker allowing it to pick
	 * up new jobs.
	 */
	public function unPauseProcessing()
	{
		$this->paused = false;
	}

	/**
	 * Schedule a worker for shutdown. Will finish processing the current job
	 * and when the timeout interval is reached, the worker will shut down.
	 */
	public function shutdown($signal = null)
	{
		$this->logger->warning("SHUTDOWN REQUESTED. SIGNAL: ".json_encode($signal));
		$this->shutdown = true;
	}
}