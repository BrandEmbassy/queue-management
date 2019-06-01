<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\JobInterface;

interface JobLoaderInterface
{
    public function loadJob(string $messageBody): JobInterface;
}
