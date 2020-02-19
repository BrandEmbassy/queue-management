<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving\DelayRules;

use BE\QueueManagement\Jobs\JobInterface;
use Throwable;

interface DelayRuleWithMilliSecondsInterface
{
    public function getDelayWithMilliSeconds(JobInterface $job, Throwable $exception): int;
}
