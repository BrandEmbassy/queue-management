<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving\DelayRules;

use BE\QueueManagement\Jobs\JobInterface;
use Throwable;

interface DelayRuleWithMillisecondsInterface
{
    public function getDelayWithMilliseconds(JobInterface $job, Throwable $exception): int;
}
