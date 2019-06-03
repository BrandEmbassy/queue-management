<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

use BE\QueueManagement\Jobs\Execution\UnresolvableProcessFailExceptionInterface;
use RuntimeException;

class UnknownJobDefinitionException extends RuntimeException implements UnresolvableProcessFailExceptionInterface
{
    public static function createFromUnknownJobName(string $jobName): self
    {
        return new self(sprintf('Job definition (%s) not found, maybe you forget to register it', $jobName));
    }
}
