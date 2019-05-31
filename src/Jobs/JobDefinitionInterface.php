<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleInterface;
use BE\QueueManagement\Jobs\Loading\JobLoaderInterface;
use BE\QueueManagement\Jobs\Processing\JobProcessorInterface;

interface JobDefinitionInterface
{
    public function getJobName(): string;


    public function getJobClass(): string;


    public function getQueueName(): string;


    public function getMaxAttempts(): ?int;


    public function getJobLoader(): JobLoaderInterface;


    public function getDelayRule(): DelayRuleInterface;


    public function getJobProcessor(): JobProcessorInterface;
}
