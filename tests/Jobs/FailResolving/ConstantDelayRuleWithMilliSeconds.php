<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\FailResolving;

use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleInterface;
use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleWithMilliSecondsInterface;
use BE\QueueManagement\Jobs\JobInterface;
use Throwable;

final class ConstantDelayRuleWithMilliSeconds implements DelayRuleInterface, DelayRuleWithMilliSecondsInterface
{
    /**
     * @var int
     */
    private $delayInMilliSeconds;


    public function __construct(int $delayInMilliSeconds)
    {
        $this->delayInMilliSeconds = $delayInMilliSeconds;
    }


    public function getDelay(JobInterface $job, Throwable $exception): int
    {
        return $this->delayInMilliSeconds / 1000;
    }


    public function getDelayWithMilliSeconds(JobInterface $job, Throwable $exception): int
    {
        return $this->delayInMilliSeconds;
    }
}
