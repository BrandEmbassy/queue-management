<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

use BE\QueueManagement\Jobs\Execution\ConsumerFailedExceptionInterface;
use RuntimeException;
use function sprintf;

class UnknownJobDefinitionException extends RuntimeException implements ConsumerFailedExceptionInterface
{
    public static function createFromUnknownJobName(string $jobName): self
    {
        return new self(sprintf('Job definition (%s) not found, maybe you forget to register it', $jobName));
    }
}
