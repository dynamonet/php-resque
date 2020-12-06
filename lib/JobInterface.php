<?php

namespace Dynamo\Resque;

interface JobInterface
{
	/**
	 * @return bool
	 */
	public function perform();
}
