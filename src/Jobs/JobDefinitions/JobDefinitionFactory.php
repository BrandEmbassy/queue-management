<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

use BE\QueueManagement\Jobs\Loading\JobLoaderInterface;

class JobDefinitionFactory implements JobDefinitionFactoryInterface
{
    /**
     * @var JobLoaderInterface
     */
    protected $defaultJobLoader;


    public function __construct(JobLoaderInterface $defaultJobLoader)
    {
        $this->defaultJobLoader = $defaultJobLoader;
    }


    public function create(string $jobName, array $jobDefinition): JobDefinitionInterface
    {
        return new JobDefinition(
            $jobName,
            $jobDefinition['class'],
            $jobDefinition['queueName'],
            $jobDefinition['maxAttempts'],
            $jobDefinition['jobLoader'] ?? $this->defaultJobLoader,
            $jobDefinition['jobDelayRule'],
            $jobDefinition['jobProcessor']
        );
    }
}
