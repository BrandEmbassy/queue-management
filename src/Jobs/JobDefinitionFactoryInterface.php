<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

interface JobDefinitionFactoryInterface
{
    public function create(string $jobName, array $jobDefinition): JobDefinitionInterface;
}
