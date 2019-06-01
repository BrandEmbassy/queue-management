<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\JobInterface;

interface JobExecutorInterface
{
    public function execute(JobInterface $job): void;
}
