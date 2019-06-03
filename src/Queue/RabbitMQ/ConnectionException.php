<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use function implode;
use RuntimeException;
use function sprintf;
use Throwable;

class ConnectionException extends RuntimeException
{
    public static function createUnableToConnect(Throwable $parentException): self
    {
        return new self('Unable to connect RabbitMQ server, try check connection parameters', 0, $parentException);
    }


    public static function createFromMissingParameters(array $missingParameters): self
    {
        return new self(
            sprintf(
                'Invalid connection config for RabbitMQ server, missing key/s (%s)',
                implode(', ', $missingParameters)
            )
        );
    }
}
