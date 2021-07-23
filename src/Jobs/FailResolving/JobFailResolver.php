<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving;

use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Queue\QueueManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function sprintf;

class JobFailResolver
{
    /**
     * @var QueueManagerInterface
     */
    private $queueManager;

    /**
     * @var LoggerInterface
     */
    private $logger;


    public function __construct(QueueManagerInterface $queueManager, LoggerInterface $logger)
    {
        $this->queueManager = $queueManager;
        $this->logger = $logger;
    }


    public function resolve(JobInterface $job, Throwable $exception): void
    {
        $job->incrementAttempts();

        $failResolveStrategy = $job->getJobDefinition()->getFailResolveStrategy();

        $pushDelayInMilliseconds = $failResolveStrategy->getDelayInMilliseconds($job, $exception);
        $queueName = $failResolveStrategy->getTargetQueueName($job, $exception);

        $this->queueManager->push($job, $pushDelayInMilliseconds, $queueName);

        $this->logger->warning(sprintf('Job requeued [delay: %.3fs]', $pushDelayInMilliseconds / 1000));
    }
}
