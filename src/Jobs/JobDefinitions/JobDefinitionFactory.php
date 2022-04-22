<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

use BE\QueueManagement\Jobs\Loading\JobLoaderInterface;

class JobDefinitionFactory implements JobDefinitionFactoryInterface
{
    protected JobLoaderInterface $defaultJobLoader;


    public function __construct(JobLoaderInterface $defaultJobLoader)
    {
        $this->defaultJobLoader = $defaultJobLoader;
    }


    /**
     * @param mixed[] $jobDefinition
     */
    public function create(string $jobName, array $jobDefinition): JobDefinitionInterface
    {
        return new JobDefinition(
            $jobName,
            $jobDefinition[self::JOB_CLASS],
            $jobDefinition[self::QUEUE_NAME],
            $jobDefinition[self::S3_BUCKET_NAME] ?? null,
            $jobDefinition[self::MAX_ATTEMPTS] ?? null,
            $jobDefinition[self::JOB_LOADER] ?? $this->defaultJobLoader,
            $jobDefinition[self::JOB_DELAY_RULE],
            $jobDefinition[self::JOB_PROCESSOR],
        );
    }
}
