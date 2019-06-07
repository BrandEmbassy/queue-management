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


    public function __construct(?JobLoaderInterface $jobLoader = null)
    {
        $this->jobLoader = $jobLoader;
    }


    public function getJobName(): string
    {
        return DummyJob::JOB_NAME;
    }


    public function getJobClass(): string
    {
        return DummyJob::class;
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
