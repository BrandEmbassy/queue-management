<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\Common;

use BE\QueueManagement\Jobs\Execution\DelayableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Execution\WarningOnlyExceptionInterface;
use Psr\Log\LoggerInterface;
use function sprintf;

/**
 * Utility class holding logging logic that requires more formatting/input preparation/etc. before actual logging
 * and at the same time is shared across multiple places (typically between RabbitMQ and AWS SQS counterparts).
 */
final class Logger
{
    public static function logDelayableProcessFailException(DelayableProcessFailExceptionInterface $exception, LoggerInterface $logger): void
    {
        $message = sprintf(
            'Job execution failed [attempts: %s], reason: %s',
            $exception->getJob()->getAttempts(),
            $exception->getMessage()
        );
        $context = [
            'exception' => $exception,
            'previousException' => $exception->getPrevious(),
        ];

        if ($exception instanceof WarningOnlyExceptionInterface) {
            $logger->warning($message, $context);

            return;
        }

        $logger->error($message, $context);
    }


    /**
     * TODO - Currently not used due to unit test issue:
     * TypeError: Argument 1 passed to BE\QueueManagement\Queue\Common\Logger::logJobPushedIntoQueue() must be an instance of BE\QueueManagement\Queue\Common\JobInterface, instance of Tests\BE\QueueManagement\Jobs\ExampleJob given
     */
    public static function logJobPushedIntoQueue(JobInterface $job, string $queueName, LoggerInterface $logger): void
    {
        $logger->info(
            sprintf(
                'Job (%s) [%s] pushed into %s queue',
                $job->getName(),
                $job->getUuid(),
                $queueName
            )
        );
    }
}
