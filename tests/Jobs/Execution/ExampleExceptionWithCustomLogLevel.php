<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\Execution\UnableToProcessLoadedJobException;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Logging\CustomLogLevelFromJobExceptionInterface;
use Psr\Log\LogLevel;

/**
 * @final
 */
class ExampleExceptionWithCustomLogLevel extends UnableToProcessLoadedJobException implements CustomLogLevelFromJobExceptionInterface
{
    public static function create(JobInterface $job): self
    {
        return new self($job, 'I will be logged as info');
    }


    public function getLogLevelForJob(JobInterface $job): string
    {
        return LogLevel::INFO;
    }
}
