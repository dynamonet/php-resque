<?php

namespace Dynamo\Resque\Commands;

use Dynamo\Resque\Jobs\JobFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class RunJobCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'job:run';

    private $jobFactory;
    private $logger;

    public function __construct(
        JobFactoryInterface $jobFactory,
        LoggerInterface $logger
    )
    {
        $this->jobFactory = $jobFactory;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
        // the short description shown while running "php bin/console list"
        ->setDescription('Run a job without pushing it to Redis')
        

        // the full command description shown when running the command with
        // the "--help" option
        ->addArgument('job_type', InputArgument::REQUIRED)
        ->addArgument('args', InputArgument::OPTIONAL, 'Job arguments')
        ->addOption('background', 'b', InputOption::VALUE_NONE, 'Run job in background');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if($input->getOption('background')){
            // run in background
            $cmd = implode(' ', ['php', 'resque', 'job:run',
                $input->getArgument('job_type'),
                $input->getArgument('args')
            ]);
            echo $cmd . PHP_EOL;
            exec("{$cmd} > /dev/null 2>&1 &");

            //$process->setOptions(['create_new_console' => true]);
            //$process->start();
            //$process->wait();
        } else {
            $job = $this->jobFactory->create(
                $input->getArgument('job_type'),
                $input->getArgument('args')
            );
            $job->setLogger($this->logger);
            $job->perform();
        }

        // ... put here the code to create the user
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
}
