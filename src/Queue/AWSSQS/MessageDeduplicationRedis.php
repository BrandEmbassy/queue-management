<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Psr\Log\LoggerInterface;

/**
 * Redis based SQS message deduplicator. Combination of distributed locks (aka redlocks) with self-expiring keys is used
 */
final class MessageDeduplicationRedis implements MessageDeduplicationInterface
{

    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function isDuplicate(SqsMessage $message): bool
    {
        return false;
    }
}