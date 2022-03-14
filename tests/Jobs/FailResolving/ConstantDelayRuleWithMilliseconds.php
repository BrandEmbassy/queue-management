<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\FailResolving;

use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleInterface;
use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleWithMillisecondsInterface;
use BE\QueueManagement\Jobs\JobInterface;
use Throwable;

/**
 * @final
 */
class ConstantDelayRuleWithMilliseconds implements DelayRuleInterface, DelayRuleWithMillisecondsInterface
{
    private int $delayInMilliseconds;


    public function __construct(int $delayInMilliseconds)
    {
        $this->delayInMilliseconds = $delayInMilliseconds;
    }


    public function getDelay(JobInterface $job, Throwable $exception): int
    {
        return $this->delayInMilliseconds / 1000;
    }


    public function getDelayWithMilliseconds(JobInterface $job, Throwable $exception): int
    {
        return $this->delayInMilliseconds;
    }
}
