<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Redis\RedisClient;
use malkusch\lock\exception\LockReleaseException;
use malkusch\lock\mutex\Mutex;
use Psr\Log\LoggerInterface;

/**
 * Default SQS message deduplicator. Combination of locks (via php-lock/lock) with self-expiring redis keys is used
 * to deliver fixed-size deduplication time window/frame for SQS message (messageId used as deduplication key).
 */
final class MessageDeduplicationDefault implements MessageDeduplicationInterface
{
    private const DEDUPLICATION_KEY_PREFIX = 'AWS_DEDUP_PREFIX_';

    private LoggerInterface $logger;

    private RedisClient $redisClient;

    private string $queueName;

    private Mutex $mutex;

    private int $deduplicationWindowSizeSec;


    public function __construct(
        LoggerInterface $logger,
        RedisClient $redisClient,
        Mutex $mutex,
        string $queueName,
        int $deduplicationWindowSizeSec = 300
    ) {
        $this->logger = $logger;
        $this->redisClient = $redisClient;
        $this->queueName = $queueName;
        $this->mutex = $mutex;
        $this->deduplicationWindowSizeSec = $deduplicationWindowSizeSec;
    }


    public function isDuplicate(SqsMessage $message): bool
    {
        $mutex = $this->mutex;

        $messageId = $message->getMessageId();
        $redisClient = $this->redisClient;
        $queueName = $this->queueName;
        $deduplicationWindowSizeSec = $this->deduplicationWindowSizeSec;

        try {
            $alreadySeen = $mutex->synchronized(function () use ($messageId, $redisClient, $queueName, $deduplicationWindowSizeSec): bool {
                $rk = self::DEDUPLICATION_KEY_PREFIX . $queueName . $messageId;
                $deduplicationKeyVal = $redisClient->get($rk);
                if ($deduplicationKeyVal === null) {
                    $redisClient->setWithTtl($rk, '1', $deduplicationWindowSizeSec);
                    return false;
                }
                return true;
            });

            return $alreadySeen;
        } catch (LockReleaseException $unlockException) {
            $codeResult = $unlockException->getCodeResult();
            $errorMessage = $unlockException->getCodeException() !== null ? $unlockException->getCodeException()->getMessage() : '';
            $this->logger->warning('Error when releasing lock ' . $errorMessage);
            if ($codeResult !== null) {
                // LockReleaseException was thrown after sync block had been already executed
                // -> use sync block return value
                return $codeResult;
            } else {
                // if code result is not known we rather prefer to process message twice
                // than to discard potentially unprocessed message -> indicate message has been not yet seen
                return false;
            }
        }
    }
}
