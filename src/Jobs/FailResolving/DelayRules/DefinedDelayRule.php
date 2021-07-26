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
    private $constantDelayDefinition;

    /**
     * @var int[]
     */
    private $linearDelayDefinition;


    /**
     * @param int[] $constantDelayDefinition [attempts limit => delay in seconds]
     * @param int[] $linearDelayDefinition   [attempts limit => delay in seconds]
     */
    public function __construct(
        int $maximumDelay,
        array $constantDelayDefinition,
        array $linearDelayDefinition
    ) {
        $this->maximumDelay = $maximumDelay;

        $this->validateDefinition($constantDelayDefinition);
        $this->validateDefinition($linearDelayDefinition);

        $this->constantDelayDefinition = $constantDelayDefinition;
        $this->linearDelayDefinition = $linearDelayDefinition;
    }


    public function getDelay(JobInterface $job, Throwable $exception): int
    {
        $currentJobAttempts = $job->getAttempts();
        $delay = 0;

        foreach ($this->getConstantDelayDefinition() as $attempts => $delayInSeconds) {
            if ($currentJobAttempts >= $attempts) {
                $delay = $delayInSeconds;
            }
        }

        foreach ($this->getLinearDelayDefinition() as $attempts => $delayInSeconds) {
            if ($currentJobAttempts >= $attempts) {
                $delay += ($currentJobAttempts - $attempts) * $delayInSeconds;
            }
        }

        return $delay > $this->maximumDelay ? $this->maximumDelay : $delay;
    }


    /**
     * @return int[]
     */
    public function getConstantDelayDefinition(): array
    {
        return $this->constantDelayDefinition;
    }


    /**
     * @return int[]
     */
    public function getLinearDelayDefinition(): array
    {
        return $this->linearDelayDefinition;
    }


    /**
     * @param int[] $delayDefinition
     */
    private function validateDefinition(array $delayDefinition): void
    {
        $attemptsDefinition = array_keys($delayDefinition);
        $attemptsDefinitionCount = count($attemptsDefinition);

        if (!in_array(0, $attemptsDefinition, true)) {
            throw DelayRuleException::byMissingDefinitionForZero();
        }

        for ($i = 1; $i < $attemptsDefinitionCount; $i++) {
            if ($attemptsDefinition[$i - 1] < $attemptsDefinition[$i]) {
                throw DelayRuleException::byIncorrectDefinitionOrder();
            }
        }
    }
}
