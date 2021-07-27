<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving\DelayRules;

use BE\QueueManagement\Jobs\JobInterface;
use Throwable;
use function array_keys;
use function count;
use function in_array;

final class DefinedDelayRule implements DelayRuleInterface
{
    /**
     * @var int
     */
    private $maximumDelay;

    /**
     * @var int[]
     */
    private $linearDelayDefinition;


    /**
     * @param int[] $linearDelayDefinition [attempts limit => delay in seconds]
     */
    public function __construct(
        int $maximumDelay,
        array $linearDelayDefinition
    ) {
        $this->maximumDelay = $maximumDelay;

        $this->validateDefinition($linearDelayDefinition);
        $this->linearDelayDefinition = $linearDelayDefinition;
    }


    public function getDelay(JobInterface $job, Throwable $exception): int
    {
        $currentJobAttempts = $job->getAttempts();

        foreach ($this->linearDelayDefinition as $definedAttemptLimits => $delayInSeconds) {
            if ($currentJobAttempts >= $definedAttemptLimits) {
                $delay = $currentJobAttempts * $delayInSeconds;

                return $delay > $this->maximumDelay ? $this->maximumDelay : $delay;
            }
        }

        throw DelayRuleException::byNotAbleToCalculateDelay();
    }


    /**
     * @param int[] $delayDefinition
     */
    private function validateDefinition(array $delayDefinition): void
    {
        $attemptsDefinition = array_keys($delayDefinition);
        $attemptsDefinitionCount = count($attemptsDefinition);

        if (!in_array(0, $attemptsDefinition, true)) {
            throw DelayRuleException::byMissingDefinitionForZeroAttempts();
        }

        for ($attemptsCount = 1; $attemptsCount < $attemptsDefinitionCount; $attemptsCount++) {
            if ($attemptsDefinition[$attemptsCount - 1] < $attemptsDefinition[$attemptsCount]) {
                throw DelayRuleException::byIncorrectDefinitionOrder();
            }
        }
    }
}
