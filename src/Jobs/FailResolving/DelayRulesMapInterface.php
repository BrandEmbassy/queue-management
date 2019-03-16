<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving;

use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleInterface;

interface DelayRulesMapInterface
{
    public function getDelayRule(string $jobName): DelayRuleInterface;
}
