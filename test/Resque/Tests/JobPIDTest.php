<?php
/**
 * PID tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Tests_JobPIDTest extends Resque_Tests_TestCase
{
	/**
	 * @var \Worker
	 */
	protected $worker;

	public function setUp()
	{
		parent::setUp();

		// Register a worker to test with
		$this->worker = new Worker('jobs');
		$this->worker->setLogger(new ConsoleLogger());
	}

	public function testQueuedJobDoesNotReturnPID()
	{
		$token = Resque::enqueue('jobs', 'Test_Job', null, true);
		$this->assertEquals(0, PID::get($token));
	}

	public function testRunningJobReturnsPID()
	{
		// Cannot use InProgress_Job on non-forking OS.
		if(!function_exists('pcntl_fork')) return;

		$token = Resque::enqueue('jobs', 'InProgress_Job', null, true);
		$this->worker->work(0);
		$this->assertNotEquals(0, PID::get($token));
	}

	public function testFinishedJobDoesNotReturnPID()
	{
		$token = Resque::enqueue('jobs', 'Test_Job', null, true);
		$this->worker->work(0);
		$this->assertEquals(0, PID::get($token));
	}
}
