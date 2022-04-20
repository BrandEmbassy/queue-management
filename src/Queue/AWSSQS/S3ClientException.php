<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use RuntimeException;
use Throwable;
use function implode;
use function sprintf;

class S3ClientException extends RuntimeException
{
    public static function createUnableToConnect(Throwable $parentException): self
    {
        return new self('Unable to connect AWS S3, try check connection parameters', 0, $parentException);
    }


    /**
     * @param string[] $missingParameters
     */
    public static function createFromMissingParameters(array $missingParameters): self
    {
        return new self(
            sprintf(
                'Invalid connection config for AWS S3, missing key/s (%s)',
                implode(', ', $missingParameters),
            ),
        );
    }
}
