<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

use BE\QueueManagement\Jobs\Execution\JobProcessorInterface;
use BE\QueueManagement\Jobs\FailResolving\FailResolveStrategy\FailResolveStrategy;
use BE\QueueManagement\Jobs\Loading\JobLoaderInterface;

interface JobDefinitionInterface
{
    public function getJobName(): string;


    public function getJobClass(): string;


    public function getQueueName(): string;


    public function getMaxAttempts(): ?int;


    public function getJobLoader(): JobLoaderInterface;


    public function getFailResolveStrategy(): FailResolveStrategy;


    public function getJobProcessor(): JobProcessorInterface;
}
