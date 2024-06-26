<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving;

use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleWithMillisecondsInterface;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Logging\LoggerContextField;
use BE\QueueManagement\Queue\QueueManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function sprintf;

class PushDelayedResolver
{
    private QueueManagerInterface $queueManager;

    private LoggerInterface $logger;


    public function __construct(QueueManagerInterface $queueManager, LoggerInterface $logger)
    {
        $this->queueManager = $queueManager;
        $this->logger = $logger;
    }


    public function resolve(JobInterface $job, Throwable $exception): void
    {
        $pushDelayInMilliseconds = $this->getDelayInMilliseconds($job, $exception);

        $job->incrementAttempts();

        $this->queueManager->pushDelayedWithMilliseconds($job, $pushDelayInMilliseconds);

        $this->logger->warning(
            sprintf('Job requeued [delay: %.3f]', $pushDelayInMilliseconds / 1000),
            [
                LoggerContextField::JOB_UUID => $job->getUuid(),
                LoggerContextField::JOB_NAME => $job->getName(),
                LoggerContextField::JOB_QUEUE_NAME => $job->getJobDefinition()->getQueueName(),
            ],
        );
    }


    private function getDelayInMilliseconds(JobInterface $job, Throwable $exception): int
    {
        $delayRule = $job->getJobDefinition()->getDelayRule();

        if ($delayRule instanceof DelayRuleWithMillisecondsInterface) {
            return $delayRule->getDelayWithMilliseconds($job, $exception);
        }

        return $delayRule->getDelay($job, $exception) * 1000;
    }
}
