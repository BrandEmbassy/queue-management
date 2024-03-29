<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS\MessageDeduplication;

use BE\QueueManagement\Logging\LoggerContextField;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use BE\QueueManagement\Redis\RedisClient;
use BrandEmbassy\DateTime\AmountOfTime\TimeInSeconds;
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

    private const DEFAULT_DEDUPLICATION_WINDOW_SIZE_IN_SECONDS = TimeInSeconds::HOUR * 2;

    private LoggerInterface $logger;

    private RedisClient $redisClient;

    private int $deduplicationWindowSizeInSeconds;


    public function __construct(
        RedisClient $redisClient,
        LoggerInterface $logger,
        int $deduplicationWindowSizeInSeconds = self::DEFAULT_DEDUPLICATION_WINDOW_SIZE_IN_SECONDS
    ) {
        $this->logger = $logger;
        $this->redisClient = $redisClient;
        $this->deduplicationWindowSizeInSeconds = $deduplicationWindowSizeInSeconds;
    }


    /**
     * @throws Throwable
     */
    public function isDuplicate(SqsMessage $message): bool
    {
        try {
            $key = sprintf(
                '%s_%s_%s',
                self::DEDUPLICATION_KEY_PREFIX,
                $this->getQueueNameFromQueueUrl($message->getQueueUrl()),
                $message->getMessageId(),
            );

            // when key was already set (result is false), it means that message was already consumed by another process
            return $this->redisClient->setWithTtl($key, '1', $this->deduplicationWindowSizeInSeconds) === false;
        } catch (Throwable $exception) {
            $this->logger->error(
                'Message duplication resolving failed.',
                [
                    LoggerContextField::EXCEPTION => $exception,
                    LoggerContextField::JOB_QUEUE_NAME => $message->getQueueUrl(),
                    LoggerContextField::MESSAGE_ID => $message->getMessageId(),
                ],
            );

            throw $exception;
        }
    }


    private function getQueueNameFromQueueUrl(string $queueUrl): string
    {
        $parts = explode('/', $queueUrl);

        return end($parts);
    }
}
