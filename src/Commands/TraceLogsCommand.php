<?php

namespace Dynamo\Resque\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TraceLogsCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'job:trace';
    protected $redis;
    protected $pubsubKey;

    /**
     * Undocumented function
     *
     * @param \Redis|\Dynamo\Redis\Client $redis
     */
    public function __construct($redis, string $pubsubKey)
    {
        $this->redis = $redis;
        $this->pubsubKey = $pubsubKey;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Trace job logs')
            ->addOption('id', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->redis->subscribe([$this->pubsubKey], function($redis, $channel, $message){
            echo "{$channel} -> {$message}\n";
        });

        while (FALSE !== ($line = fgets(STDIN)));

        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable

        // return this if there was no problem running the command
        // (it's equivalent to returning int(0))
        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }

    protected function onPubsub($redis, $channel, $message)
    {
        echo "{$channel} -> {$message}\n";
    }
}
