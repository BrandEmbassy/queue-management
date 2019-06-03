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
    public const JOB_NAME = 'dummyJob';
    public const QUEUE_NAME = 'dummyJobQueue';


    public function getJobName(): string
    {
        return self::JOB_NAME;
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
        throw new RuntimeException('Not implemented');
    }


    public function getJobLoader(): JobLoaderInterface
    {
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
