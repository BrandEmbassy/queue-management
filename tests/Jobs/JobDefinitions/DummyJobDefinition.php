<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\JobDefinitions;

use BE\QueueManagement\Jobs\Execution\JobProcessorInterface;
use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleInterface;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use BE\QueueManagement\Jobs\Loading\JobLoaderInterface;
use RuntimeException;
use Tests\BE\QueueManagement\Jobs\DummyJob;

class DummyJobDefinition implements JobDefinitionInterface
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


    public function __construct(
        ?JobLoaderInterface $jobLoader = null,
        string $jobName = DummyJob::JOB_NAME,
        string $jobClass = DummyJob::class
    ) {
        $this->jobLoader = $jobLoader;
        $this->jobName = $jobName;
        $this->jobClass = $jobClass;
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
        throw new RuntimeException('Not implemented');
    }


    public function getJobProcessor(): JobProcessorInterface
    {
        throw new RuntimeException('Not implemented');
    }
}
