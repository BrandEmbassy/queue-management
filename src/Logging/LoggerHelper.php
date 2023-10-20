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
    public const UNKNOWN_DELAY = -1;
    public const NOT_DELAYED = 0;


    public static function logDelayableProcessFailException(
        DelayableProcessFailExceptionInterface $exception,
        LoggerInterface $logger
    ): void {
        $job = $exception->getJob();
        $message = sprintf(
            'Job execution failed [attempts: %s], reason: %s',
            $job->getAttempts(),
            $exception->getMessage(),
        );
        $context = [
            LoggerContextField::EXCEPTION => $exception,
            LoggerContextField::JOB_QUEUE_NAME => $job->getJobDefinition()->getQueueName(),
            LoggerContextField::JOB_NAME => $job->getName(),
            LoggerContextField::JOB_UUID => $job->getUuid(),
        ];

        // JobExecutor remaps the thrown exception to UnableToProcessLoadedJobException so we need to also check the previous exception
        if ($exception instanceof WarningOnlyExceptionInterface
            || $exception->getPrevious() instanceof WarningOnlyExceptionInterface) {
            $logger->warning($message, $context);

            return;
        }

        $logger->error($message, $context);
    }


    public static function logJobPushedIntoQueue(
        JobInterface $job,
        string $queueName,
        LoggerInterface $logger,
        ?JobType $jobType = null,
        int $delayInSeconds = self::UNKNOWN_DELAY,
        string $sqsMessageId = 'unknown'
    ): void {
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
                LoggerContextField::JOB_DELAY_IN_SECONDS => $delayInSeconds,
                LoggerContextField::MESSAGE_ID => $sqsMessageId,
            ],
        );
    }
}
