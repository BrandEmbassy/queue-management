<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS\SqsScheduler;

use BE\QueueManagement\Jobs\JobInterface;

interface DelayedJobSchedulerInterface
{
    public function getSchedulerName(): string;


    public function scheduleJob(JobInterface $job, string $prefixedQueueName): string;
}
