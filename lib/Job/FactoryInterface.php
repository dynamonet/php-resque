<?php

namespace Dynamo\Resque\Job;

use Dynamo\Resque\JobInterface;

interface FactoryInterface
{
	/**
	 * @param $className
	 * @param array $args
	 * @param $queue
	 * @return JobInterface
	 */
	public function create($className, $args, $queue) : JobInterface;

	/**
	 * @param $rawPayload
	 * @param $queue The queue from which the payload came from
	 * @return JobInterface
	 */
	public function parse(string $rawPayload, string $queue): JobInterface;
}
