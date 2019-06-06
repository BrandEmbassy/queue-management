<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\Execution\UnresolvableProcessFailExceptionInterface;
use Exception;
use function sprintf;

class BlacklistedJobUuidException extends Exception implements UnresolvableProcessFailExceptionInterface
{
    public static function createFromJobUuid(string $jobUuid): self
    {
        return new self(sprintf('Job %s blacklisted', $jobUuid));
    }
}
