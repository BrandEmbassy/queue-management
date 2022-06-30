<?php declare(strict_types = 1);

namespace BE\QueueManagement\Logging;

use BE\QueueManagement\Jobs\Execution\DelayableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Execution\WarningOnlyExceptionInterface;
use BE\QueueManagement\Jobs\JobInterface;
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
        $message = sprintf(
            'Job execution failed [attempts: %s], reason: %s',
            $exception->getJob()->getAttempts(),
            $exception->getMessage(),
        );
        $context = [
            LoggerContextField::EXCEPTION => $exception,
            LoggerContextField::PREVIOUS_EXCEPTION => $exception->getPrevious(),
        ];

        if ($exception instanceof WarningOnlyExceptionInterface) {
            $logger->warning($message, $context);

            return;
        }

        $logger->error($message, $context);
    }


    public static function logJobPushedIntoQueue(JobInterface $job, string $queueName, LoggerInterface $logger): void
    {
        $logger->info(
            sprintf(
                'Job (%s) [%s] pushed into %s queue',
                $job->getName(),
                $job->getUuid(),
                $queueName,
            ),
        );
    }
}
