<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving\DelayRules;

use BE\QueueManagement\Jobs\JobInterface;
use Throwable;

class ConstantDelayRule implements DelayRuleInterface
{
    /**
     * @var int
     */
    private $constantDelayInSeconds;


    public function __construct(int $constantDelayInSeconds)
    {
        $this->constantDelayInSeconds = $constantDelayInSeconds;
    }


    public function getDelay(JobInterface $job, Throwable $exception): int
    {
        return $this->constantDelayInSeconds;
    }
}
