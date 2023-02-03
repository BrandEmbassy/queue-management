<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\Execution\UnableToProcessLoadedJobException;
use BE\QueueManagement\Jobs\JobInterface;

/**
 * @final
 */
class ExampleExceptionWithPreviousWarningOnlyException extends UnableToProcessLoadedJobException
{
    public static function create(JobInterface $job): self
    {
        $previous = ExampleWarningOnlyException::create($job);

        return new self($job, 'This is ExampleUnableToProcessLoadedJobException.', 400, $previous);
    }
}
