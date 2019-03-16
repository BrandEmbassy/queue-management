<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Processing;

use BE\QueueManagement\Jobs\JobInterface;

interface AfterProcessHandlerInterface
{
    public function after(JobInterface $job): void;
}
