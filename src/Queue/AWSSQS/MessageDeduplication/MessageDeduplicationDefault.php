<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS\MessageDeduplication;

use BE\QueueManagement\Logging\LoggerContextField;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use BE\QueueManagement\Redis\RedisClient;
use Exception;
use malkusch\lock\exception\LockReleaseException;
use malkusch\lock\mutex\Mutex;
use Psr\Log\LoggerInterface;

/**
 * Default SQS message deduplicator. Combination of locks (via php-lock/lock) with self-expiring redis keys is used
 * to deliver fixed-size deduplication time window/frame for SQS message (messageId used as deduplication key).
 *
 * @final
 */
class MessageDeduplicationDefault implements MessageDeduplication
{
    private const DEDUPLICATION_KEY_PREFIX = 'AWS_DEDUP_PREFIX_';

    private LoggerInterface $logger;

    private RedisClient $redisClient;

    private string $queueName;

    private Mutex $mutex;

    private int $deduplicationWindowSizeInSeconds;


    public function __construct(
        LoggerInterface $logger,
        RedisClient $redisClient,
        Mutex $mutex,
        string $queueName,
        int $deduplicationWindowSizeInSeconds = 300
    ) {
        $this->logger = $logger;
        $this->redisClient = $redisClient;
        $this->queueName = $queueName;
        $this->mutex = $mutex;
        $this->deduplicationWindowSizeInSeconds = $deduplicationWindowSizeInSeconds;
    }


    /**
     * @throws Exception
     */
    public function isDuplicate(SqsMessage $message): bool
    {
        try {
            return $this->mutex->synchronized(function () use ($message): bool {
                $key = self::DEDUPLICATION_KEY_PREFIX . $this->queueName . $message->getMessageId();
                $deduplicationKeyVal = $this->redisClient->get($key);
                if ($deduplicationKeyVal === null) {
                    $this->redisClient->setWithTtl($key, '1', $this->deduplicationWindowSizeInSeconds);

                    return false;
                }

                return true;
            });
        } catch (LockReleaseException $exception) {
            $codeResult = $exception->getCodeResult();
            $errorMessage = $exception->getCodeException() !== null
                ? $exception->getCodeException()->getMessage()
                : 'exception message not available';
            $this->logger->warning(
                'Error when releasing lock: ' . $errorMessage,
                [
                    LoggerContextField::EXCEPTION => (string)$exception,
                ],
            );
            if ($codeResult !== null) {
                // LockReleaseException was thrown after sync block had been already executed
                // -> use sync block return value
                return $codeResult;
            }

            // we rather prefer to process message twice than to discard potentially unprocessed message
            $this->logger->warning(
                'Code result unavailable when releasing lock, ' .
                'assuming false to indicate the message has not been seen yet.',
            );

            return false;
        }
    }
}