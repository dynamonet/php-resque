<?php

namespace Dynamo\Resque\Jobs;

/**
 * Dummy job for testing purposes. All it does is count to n
 */
class SleepJob extends Job {

    public function perform()
    {
        $n = (int) $this->args;
        for($i = 1; $i <= $n; $i++){
            $this->logger->info("{$i} out of {$n}");
            sleep(1);
        }
        
    }
}