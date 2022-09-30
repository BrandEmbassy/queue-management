<?php declare(strict_types = 1);

namespace BE\QueueManagement\Logging;

use BE\QueueManagement\Jobs\Execution\DelayableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Execution\WarningOnlyExceptionInterface;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\JobType;
use Psr\Log\LoggerInterface;
use function sprintf;

/**
 * Utility class holding logging logic that requires more formatting/input preparation/etc. before actual logging
 * and at the same time is shared across multiple places (typically between RabbitMQ and AWS SQS counterparts).
 *
 * @final
 */
class LoggerHelper
{
    public static function logDelayableProcessFailException(DelayableProcessFailExceptionInterface $exception, LoggerInterface $logger): void
    {
        $job = $exception->getJob();
        $message = sprintf(
            'Job execution failed [attempts: %s], reason: %s',
            $job->getAttempts(),
            $exception->getMessage(),
        );
        $context = [
            LoggerContextField::EXCEPTION => $exception,
            LoggerContextField::JOB_QUEUE_NAME => $job->getJobDefinition()->getQueueName(),
            LoggerContextField::JOB_NAME => $job->getJobDefinition()->getJobName(),
            LoggerContextField::JOB_UUID => $job->getUuid(),
        ];

        if ($exception instanceof WarningOnlyExceptionInterface) {
            $logger->warning($message, $context);

            return;
        }

        $logger->error($message, $context);
    }


    public static function logJobPushedIntoQueue(JobInterface $job, string $queueName, LoggerInterface $logger, ?JobType $jobType = null): void
    {
        $logger->info(
            sprintf(
                'Job (%s) [%s] pushed into %s queue',
                $job->getName(),
                $job->getUuid(),
                $queueName,
            ),
            [
                LoggerContextField::JOB_QUEUE_NAME => $queueName,
                LoggerContextField::JOB_NAME => $job->getName(),
                LoggerContextField::JOB_UUID => $job->getUuid(),
                LoggerContextField::JOB_TYPE => $jobType === null ? JobType::UNKNOWN : $jobType->getValue(),
            ],
        );
    }
}
