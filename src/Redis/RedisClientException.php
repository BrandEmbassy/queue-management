<?php declare(strict_types = 1);

namespace BE\QueueManagement\Redis;

use RuntimeException;
use Throwable;
use function sprintf;

/**
 * @final
 */
class RedisClientException extends RuntimeException
{
    public static function byAnotherExceptionWhenSettingValueWithTtl(Throwable $exception): self
    {
        $message = sprintf(
            'Unexpected exception during setting of value with time to live: %s.',
            $exception->getMessage(),
        );

        return new self($message, $exception->getCode(), $exception);
    }


    public static function byInvalidResultStatus(string $result): self
    {
        return new self(
            sprintf('Invalid response from Redis client during value setting. Response value: %s.', $result),
        );
    }


    public static function byInvalidSavedStatus(string $resultPayloadMessage): self
    {
        return new self(sprintf('Saving failed. Response payload value: %s.', $resultPayloadMessage));
    }
}
