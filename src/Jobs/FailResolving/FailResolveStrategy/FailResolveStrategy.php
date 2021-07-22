<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving\FailResolveStrategy;

use BE\QueueManagement\Jobs\JobInterface;
use Throwable;

interface FailResolveStrategy
{
    public function getDelayInMilliseconds(JobInterface $job, Throwable $exception): int;


    public function getQueueName(JobInterface $job, Throwable $exception): string;
}
