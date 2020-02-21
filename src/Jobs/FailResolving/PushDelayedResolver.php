<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving;

use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleWithMilliSecondsInterface;
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

        $pushDelayInMilliSeconds = $this->getDelayInMilliSeconds($job, $exception);

        $this->queueManager->pushDelayedWithMilliSeconds($job, $pushDelayInMilliSeconds);

        $this->logger->warning(sprintf('Job requeued [delay: %.3f]', $pushDelayInMilliSeconds / 1000));
    }


    private function getDelayInMilliSeconds(JobInterface $job, Throwable $exception): int
    {
        $delayRule = $job->getJobDefinition()->getDelayRule();

        if ($delayRule instanceof DelayRuleWithMilliSecondsInterface) {
            return $delayRule->getDelayWithMilliSeconds($job, $exception);
        }

        return $delayRule->getDelay($job, $exception) * 1000;
    }
}
