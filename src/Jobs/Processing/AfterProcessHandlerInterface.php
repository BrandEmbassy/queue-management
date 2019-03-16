<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Processing;

use BE\QueueManagement\Jobs\JobInterface;

interface AfterProcessHandlerInterface
{
    public function __invoke(JobInterface $job): void;
}
