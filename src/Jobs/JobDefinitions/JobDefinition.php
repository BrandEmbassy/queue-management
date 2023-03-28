<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

use BE\QueueManagement\Jobs\Execution\JobProcessorInterface;
use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleInterface;
use BE\QueueManagement\Jobs\Loading\JobLoaderInterface;

/**
 * @phpstan-type TJobDefinition array{
 *     class: string,
 *     queueName: string,
 *     maxAttempts?: ?int,
 *     jobLoader?: ?JobLoaderInterface,
 *     jobDelayRule: DelayRuleInterface,
 *     jobProcessor: JobProcessorInterface
 * }
 */
class JobDefinition implements JobDefinitionInterface
{
    private string $jobClass;

    private string $queueName;

    private ?int $maxAttempts;

    private JobLoaderInterface $jobLoader;

    private DelayRuleInterface $delayRule;

    private JobProcessorInterface $jobProcessor;

    private string $jobName;


    public function __construct(
        string $jobName,
        string $jobClass,
        string $queueName,
        ?int $maxAttempts,
        JobLoaderInterface $jobLoader,
        DelayRuleInterface $delayRule,
        JobProcessorInterface $jobProcessor
    ) {
        $this->jobName = $jobName;
        $this->jobClass = $jobClass;
        $this->queueName = $queueName;
        $this->maxAttempts = $maxAttempts;
        $this->jobLoader = $jobLoader;
        $this->delayRule = $delayRule;
        $this->jobProcessor = $jobProcessor;
    }


    public function getJobName(): string
    {
        return $this->jobName;
    }


    public function getJobClass(): string
    {
        return $this->jobClass;
    }


    public function getQueueName(): string
    {
        return $this->queueName;
    }


    public function getMaxAttempts(): ?int
    {
        return $this->maxAttempts;
    }


    public function getJobLoader(): JobLoaderInterface
    {
        return $this->jobLoader;
    }


    public function getDelayRule(): DelayRuleInterface
    {
        return $this->delayRule;
    }


    public function getJobProcessor(): JobProcessorInterface
    {
        return $this->jobProcessor;
    }
}
