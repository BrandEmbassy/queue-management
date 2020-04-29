<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving\DelayRules;

use BE\QueueManagement\Jobs\JobInterface;
use Throwable;

class ConstantDelayRule implements DelayRuleInterface
{
    /**
     * @var int
     */
    private $constantDelay;


    public function __construct(int $constantDelay)
    {
        $this->constantDelay = $constantDelay;
    }


    public function getDelay(JobInterface $job, Throwable $exception): int
    {
        return $this->constantDelay;
    }
}
