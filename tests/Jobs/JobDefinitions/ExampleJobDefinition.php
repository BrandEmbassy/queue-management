<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\JobDefinitions;

use BE\QueueManagement\Jobs\Execution\JobProcessorInterface;
use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleInterface;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use BE\QueueManagement\Jobs\Loading\JobLoaderInterface;
use RuntimeException;
use Tests\BE\QueueManagement\Jobs\ExampleJob;

class ExampleJobDefinition implements JobDefinitionInterface
{
    public const QUEUE_NAME = 'dummyJobQueue';
    public const MAX_ATTEMPTS = 3;

    /**
     * @var JobLoaderInterface|null
     */
    private $jobLoader;

    /**
     * @var string
     */
    private $jobName;

    /**
     * @var string
     */
    private $jobClass;

    /**
     * @var DelayRuleInterface|null
     */
    private $delayRule;

    /**
     * @var JobProcessorInterface|null
     */
    private $jobProcessor;


    public function __construct(string $jobName, string $jobClass)
    {
        $this->jobName = $jobName;
        $this->jobClass = $jobClass;
    }


    public static function create(string $jobName = ExampleJob::JOB_NAME, string $jobClass = ExampleJob::class): self
    {
        return new self($jobName, $jobClass);
    }


    public function withJobLoader(JobLoaderInterface $jobLoader): self
    {
        $clone = clone $this;
        $clone->jobLoader = $jobLoader;

        return $clone;
    }


    public function withDelayRule(DelayRuleInterface $delayRule): self
    {
        $clone = clone $this;
        $clone->delayRule = $delayRule;

        return $clone;
    }


    public function withJobProcessor(JobProcessorInterface $jobProcessor): self
    {
        $clone = clone $this;
        $clone->jobProcessor = $jobProcessor;

        return $clone;
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
        return self::QUEUE_NAME;
    }


    public function getMaxAttempts(): ?int
    {
        return self::MAX_ATTEMPTS;
    }


    public function getJobLoader(): JobLoaderInterface
    {
        if ($this->jobLoader !== null) {
            return $this->jobLoader;
        }

        throw new RuntimeException('Not implemented');
    }


    public function getDelayRule(): DelayRuleInterface
    {
        if ($this->delayRule !== null) {
            return $this->delayRule;
        }

        throw new RuntimeException('Not implemented');
    }


    public function getJobProcessor(): JobProcessorInterface
    {
        if ($this->jobProcessor !== null) {
            return $this->jobProcessor;
        }

        throw new RuntimeException('Not implemented');
    }
}
