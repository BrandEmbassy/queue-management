<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Processing;

use BE\QueueManagement\Jobs\JobInterface;

interface BeforeProcessHandlerInterface
{
    public function before(JobInterface $job): void;
}
