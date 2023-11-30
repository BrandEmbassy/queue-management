<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\Execution\UnableToProcessLoadedJobException;
use BE\QueueManagement\Jobs\JobInterface;

/**
 * @final
 */
class ExampleExceptionWithPreviousCustomLogLevelException extends UnableToProcessLoadedJobException
{
    public static function create(JobInterface $job): self
    {
        $previous = new ExampleExceptionWithCustomLogLevel($job, 'I will be logged as a info');

        return new self($job, 'This is ExampleUnableToProcessLoadedJobException.', 400, $previous);
    }
}
