<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\Execution\JobProcessorInterface;
use BE\QueueManagement\Jobs\JobInterface;

/**
 * @final
 */
class ExampleJobProcessor implements JobProcessorInterface
{
    public function process(JobInterface $job): void
    {
    }
}
