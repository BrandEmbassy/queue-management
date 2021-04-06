<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\Execution\UnableToProcessLoadedJobException;
use BE\QueueManagement\Jobs\Execution\WarningOnlyExceptionInterface;
use BE\QueueManagement\Jobs\JobInterface;

final class ExampleWarningOnlyException extends UnableToProcessLoadedJobException implements WarningOnlyExceptionInterface
{
    public static function create(JobInterface $job): self
    {
        return new self($job, 'I will be logged as a warning');
    }
}
