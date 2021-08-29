<?php

namespace Dynamo\Resque\Jobs;

/**
 * Dummy job for testing purposes. All it does is count to n
 */
class SleepJob extends Job {

    public function perform()
    {
        $n = (int) $this->args;
        echo "Counting to {$n}\n";
        for($i = 1; $i <= $n; $i++){
            echo "{$i}/{$n}\n";
            sleep(1);
        }
        
    }
}