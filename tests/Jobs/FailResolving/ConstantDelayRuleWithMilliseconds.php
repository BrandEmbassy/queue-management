<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\FailResolving;

use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleInterface;
use BE\QueueManagement\Jobs\JobInterface;
use Throwable;

final class ConstantDelayRuleWithMilliseconds implements DelayRuleInterface
{
    /**
     * @var int
     */
    private $delayInMilliseconds;


    public function __construct(int $delayInMilliseconds)
    {
        $this->delayInMilliseconds = $delayInMilliseconds;
    }


    public function getDelayInMilliseconds(JobInterface $job, Throwable $exception): int
    {
        return $this->delayInMilliseconds;
    }
}
