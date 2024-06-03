<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving\DelayRules;

use BE\QueueManagement\Jobs\JobInterface;
use Throwable;

/**
 * @final
 */
class ExponentialDelayRule implements DelayRuleInterface, DelayRuleWithMillisecondsInterface
{
    public function __construct(
        private readonly int $initialDelayInMilliseconds,
        private readonly int $maximumDelayInMilliseconds,
    ) {
    }


    public function getDelay(JobInterface $job, Throwable $exception): int
    {
        $delayInSeconds = $this->getDelayWithMilliseconds($job, $exception) / 1000;

        return (int)$delayInSeconds;
    }


    public function getDelayWithMilliseconds(JobInterface $job, Throwable $exception): int
    {
        $attempts = $job->getAttempts();
        $exponentialMultiplier = 2 ** ($attempts - 1);

        $delayInMilliseconds = $exponentialMultiplier * $this->initialDelayInMilliseconds;

        return $delayInMilliseconds > $this->maximumDelayInMilliseconds
            ? $this->maximumDelayInMilliseconds
            : (int)$delayInMilliseconds;
    }
}
