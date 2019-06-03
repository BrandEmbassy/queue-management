<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Execution;

use RuntimeException;
use function sprintf;

class MaximumAttemptsExceededException extends RuntimeException implements UnresolvableProcessFailExceptionInterface
{
    public static function createFromAttemptsLimit(int $maxAttempts): self
    {
        return new self(sprintf('Maximum limit (%s) attempts exceeded', $maxAttempts));
    }
}
