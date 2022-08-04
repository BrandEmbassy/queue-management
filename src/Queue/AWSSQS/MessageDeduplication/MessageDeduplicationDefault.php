<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS\MessageDeduplication;

use BE\QueueManagement\Logging\LoggerContextField;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use BE\QueueManagement\Redis\RedisClient;
use Exception;
use malkusch\lock\exception\LockReleaseException;
use malkusch\lock\mutex\Mutex;
use Psr\Log\LoggerInterface;
use Throwable;
use function end;
use function explode;
use function sprintf;

/**
 * Default SQS message deduplicator. Combination of locks (via php-lock/lock) with self-expiring redis keys is used
 * to deliver fixed-size deduplication time window/frame for SQS message (messageId used as deduplication key).
 *
 * @final
 */
class MessageDeduplicationDefault implements MessageDeduplication
{
    private const DEDUPLICATION_KEY_PREFIX = 'AWS_DEDUP_PREFIX';

    private LoggerInterface $logger;

    private RedisClient $redisClient;

    private Mutex $mutex;

    private int $deduplicationWindowSizeInSeconds;


    public function __construct(
        RedisClient $redisClient,
        Mutex $mutex,
        LoggerInterface $logger,
        int $deduplicationWindowSizeInSeconds = 300
    ) {
        $this->logger = $logger;
        $this->redisClient = $redisClient;
        $this->mutex = $mutex;
        $this->deduplicationWindowSizeInSeconds = $deduplicationWindowSizeInSeconds;
    }


    /**
     * @throws Exception
     */
    public function isDuplicate(SqsMessage $message): bool
    {
        try {
            $this->tryClient($message);

            return $this->mutex->synchronized(function () use ($message): bool {
                $this->logger->debug('synchronize');

                return true;
                /*
                $key = sprintf(
                    '%s_%s_%s',
                    self::DEDUPLICATION_KEY_PREFIX,
                    $this->getQueueNameFromQueueUrl($message->getQueueUrl()),
                    $message->getMessageId(),
                );
                $deduplicationKeyVal = $this->redisClient->get($key);
                if ($deduplicationKeyVal === null) {
                    $this->redisClient->setWithTtl($key, '1', $this->deduplicationWindowSizeInSeconds);

                    return false;
                }

                return true;
                */
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
                    LoggerContextField::JOB_QUEUE_NAME => $message->getQueueUrl(),
                    LoggerContextField::MESSAGE_ID => $message->getMessageId(),
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
                [
                    LoggerContextField::JOB_QUEUE_NAME => $message->getQueueUrl(),
                    LoggerContextField::MESSAGE_ID => $message->getMessageId(),
                ],
            );

            return false;
        } catch (Throwable $exception) {
            $this->logger->error(
                'Message duplication resolving failed',
                [
                    LoggerContextField::EXCEPTION => (string)$exception,
                    LoggerContextField::JOB_QUEUE_NAME => $message->getQueueUrl(),
                    LoggerContextField::MESSAGE_ID => $message->getMessageId(),
                ],
            );

            throw $exception;
        }
    }


    private function tryClient(SqsMessage $message): void
    {
        $key = sprintf(
            '%s_%s_%s_test',
            self::DEDUPLICATION_KEY_PREFIX,
            $this->getQueueNameFromQueueUrl($message->getQueueUrl()),
            $message->getMessageId(),
        );

        $deduplicationKeyVal = $this->redisClient->get($key);
        $this->logger->debug(
            sprintf('get value key %s in redis client, value: %s', $key, (string)$deduplicationKeyVal)
        );
        if ($deduplicationKeyVal === null) {
            $this->redisClient->setWithTtl($key, '1', $this->deduplicationWindowSizeInSeconds);
            $this->logger->debug(sprintf('set key %s in redis client', $key));
            $deduplicationKeyVal = $this->redisClient->get($key);
            $this->logger->debug(
                sprintf('get value key %s in redis client after setting, value: %s', $key, (string)$deduplicationKeyVal)
            );
        }
    }


    private function getQueueNameFromQueueUrl(string $queueUrl): string
    {
        $parts = explode('/', $queueUrl);

        return end($parts);
    }
}
