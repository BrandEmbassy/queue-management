<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving;

use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Queue\QueueManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function sprintf;

class PushDelayedResolver
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

        $delayRule = $job->getJobDefinition()->getDelayRule();

        $pushDelay = $delayRule->getDelay($job, $exception);

        $this->queueManager->pushDelayed($job, $pushDelay);

        $this->logger->warning(sprintf('Job requeued [delay: %d]', $pushDelay));
    }
}
