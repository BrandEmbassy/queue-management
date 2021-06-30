<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving\DelayRules;

use BE\QueueManagement\Jobs\JobInterface;
use Throwable;

interface DifferentQueueDelayRuleInterface
{
    public function getDelayQueueName(JobInterface $job, Throwable $exception): string;
}
