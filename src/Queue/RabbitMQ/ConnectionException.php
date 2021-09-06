<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use RuntimeException;
use Throwable;
use function implode;
use function sprintf;

class ConnectionException extends RuntimeException
{
    public static function createUnableToConnect(Throwable $parentException): self
    {
        return new self('Unable to connect RabbitMQ server, try check connection parameters', 0, $parentException);
    }


    /**
     * @param string[] $missingParameters
     */
    public static function createFromMissingParameters(array $missingParameters): self
    {
        return new self(
            sprintf(
                'Invalid connection config for RabbitMQ server, missing key/s (%s)',
                implode(', ', $missingParameters)
            )
        );
    }


    public static function createMaximumReconnectLimitReached(int $reconnectsLimit): self
    {
        return new self(
            sprintf('Maximum reconnects limit (%d) reached', $reconnectsLimit)
        );
    }
}
