<?php

namespace Dynamo\Resque\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Dynamo\Resque\Manager;

class PushJobCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'job:push';

    /**
     * Resque manager
     *
     * @var \Dynamo\Resque\Manager
     */
    protected $manager;

    public function __construct(
        Manager $manager
    )
    {
        $this->manager = $manager;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
        // the short description shown while running "php bin/console list"
        ->setDescription('Push job')

        // the full command description shown when running the command with
        // the "--help" option
        ->addArgument('job_type', InputArgument::REQUIRED)
        ->addArgument('args', InputArgument::OPTIONAL, 'Job arguments')
        ->addOption('id', null, InputOption::VALUE_OPTIONAL, "Job ID", '*')
        ->addOption('worker', 'w', InputOption::VALUE_OPTIONAL)
        ->addOption('queue', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // ... put here the code to create the user
        echo "Pushing job of type '{$input->getArgument('job_type')}' to worker {$input->getOption('worker')}\n";

        // this method must return an integer number with the "exit status code"
        $this->manager->push(
            $input->getArgument('job_type'),
            $input->getOption('id'),
            $input->getArgument('args')
        );
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
