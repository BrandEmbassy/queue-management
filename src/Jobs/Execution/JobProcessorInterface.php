<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\JobInterface;

interface JobProcessorInterface
{
    public function process(JobInterface $job): void;
}
