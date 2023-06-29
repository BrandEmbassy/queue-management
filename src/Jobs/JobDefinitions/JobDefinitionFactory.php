<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

use BE\QueueManagement\Jobs\Loading\JobLoaderInterface;

/**
 * @phpstan-import-type TJobDefinition from JobDefinition
 */
class JobDefinitionFactory implements JobDefinitionFactoryInterface
{
    protected JobLoaderInterface $defaultJobLoader;

    private string $queueNamePrefix;


    public function __construct(JobLoaderInterface $defaultJobLoader, string $queueNamePrefix = '')
    {
        $this->defaultJobLoader = $defaultJobLoader;
        $this->queueNamePrefix = $queueNamePrefix;
    }


    /**
     * @param TJobDefinition $jobDefinition
     */
    public function create(string $jobName, array $jobDefinition): JobDefinitionInterface
    {
        return new JobDefinition(
            $jobName,
            $jobDefinition[self::JOB_CLASS],
            $this->queueNamePrefix . $jobDefinition[self::QUEUE_NAME],
            $jobDefinition[self::MAX_ATTEMPTS] ?? null,
            $jobDefinition[self::JOB_LOADER] ?? $this->defaultJobLoader,
            $jobDefinition[self::JOB_DELAY_RULE],
            $jobDefinition[self::JOB_PROCESSOR],
        );
    }
}
