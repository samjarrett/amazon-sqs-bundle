<?php

namespace Cotd\AmazonSqsBundle\Command;

use Cotd\AmazonSqsBundle\QueueManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunnerCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('sqs:runner')
            ->setDescription('TEST COMMAND')
            ->addArgument('queue', InputArgument::REQUIRED, 'The queue name')
            ->addOption('wait-time', null, InputOption::VALUE_REQUIRED, 'The amount of time to wait for a job before exiting', QueueManager::DEFAULT_WAIT_TIME)
            ->addOption('worker-time', null, InputOption::VALUE_REQUIRED, 'The allowed time for a worker to complete a single job before AWS releases it back to the queue', QueueManager::DEFAULT_WORKER_TIME)
            ->addOption('sleep-if-empty', null, InputOption::VALUE_REQUIRED, 'How long to wait (in seconds) if there are no jobs', 0)
            ->addOption('jobs', null, InputOption::VALUE_REQUIRED, 'How many jobs to fetch', QueueManager::DEFAULT_JOB_COUNT)
            ->addOption('check-termination', null, InputOption::VALUE_NONE, 'Should this check for AWS spot instance\'s being terminated?')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('check-termination')) {
            $this->checkForTerminationTime($output);
        }

        /** @var QueueManager $queue */
        $queue = $this->getContainer()->get('cotd_amazon_sqs.queue_manager.' . $input->getArgument('queue'));

        try {
            $processed = $queue->getTask($input->getOption('wait-time'), $input->getOption('worker-time'), $input->getOption('jobs'));

            $this->waitBeforeExiting($input, $processed);
        } catch (\Exception $e) {
            // Wait out the timer before throwing the exception to prevent rapid-fire retries
            $this->waitBeforeExiting($input, null, true);

            throw $e;
        }
    }

    protected function waitBeforeExiting(InputInterface $input, $processedItems, $exception = false)
    {
        $sleepTime = $input->getOption('sleep-if-empty');

        if ($sleepTime > 0 && ($processedItems == 0 || $exception)) {
            sleep($sleepTime);
        }
    }

    protected function checkForTerminationTime(OutputInterface $output)
    {
        try {
            $client = new Client();

            $response = $client->get('http://169.254.169.254/latest/meta-data/spot/termination-time');
        } catch (RequestException $e) {
            // return silently
            $response = $e->getResponse();
        }

        if ($response->getStatusCode() != '404') {
            $output->writeln('Detected spot instance shutdown in progress, halting execution');

            sleep(5 * 60);

            exit(1);
        }
    }
}
