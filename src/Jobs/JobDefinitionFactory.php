<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

class JobDefinitionFactory implements JobDefinitionFactoryInterface
{
    public function create(string $jobName, array $jobDefinition): JobDefinitionInterface
    {
        return new JobDefinition(
            $jobName,
            $jobDefinition['class'],
            $jobDefinition['queueName'],
            $jobDefinition['maxAttempts'],
            $jobDefinition['jobLoader'],
            $jobDefinition['jobDelayRule'],
            $jobDefinition['jobProcessor']
        );
    }
}
