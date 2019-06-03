<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

interface JobDefinitionFactoryInterface
{
    public const JOB_CLASS = 'class';
    public const QUEUE_NAME = 'queueName';
    public const MAX_ATTEMPTS = 'maxAttempts';
    public const JOB_LOADER = 'jobLoader';
    public const JOB_DELAY_RULE = 'jobDelayRule';
    public const JOB_PROCESSOR = 'jobProcessor';


    public function create(string $jobName, array $jobDefinition): JobDefinitionInterface;
}
