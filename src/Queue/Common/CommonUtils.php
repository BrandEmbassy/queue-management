<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\Common;

use BE\QueueManagement\Jobs\Execution\DelayableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Execution\WarningOnlyExceptionInterface;
use Psr\Log\LoggerInterface;
use function sprintf;

class CommonUtils
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
}
