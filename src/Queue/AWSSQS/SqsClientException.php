<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use RuntimeException;
use Throwable;
use function implode;
use function sprintf;

/**
 * @final
 */
class SqsClientException extends RuntimeException
{
    public static function createUnableToConnect(Throwable $parentException): self
    {
        return new self('Unable to connect AWS SQS, try check connection parameters', 0, $parentException);
    }


    /**
     * @param string[] $missingParameters
     */
    public static function createFromMissingParameters(array $missingParameters): self
    {
        return new self(
            sprintf(
                'Invalid connection config for AWS SQS, missing key/s (%s)',
                implode(', ', $missingParameters),
            ),
        );
    }


    public static function createFromInvalidDelaySeconds(int $delay): self
    {
        return new self(
            sprintf('Invalid DelaySeconds specified: (%d). Value must be integer from 0 to 900 seconds.', $delay),
        );
    }
}
