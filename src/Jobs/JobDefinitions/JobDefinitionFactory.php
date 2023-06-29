<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

use BE\QueueManagement\Jobs\Loading\JobLoaderInterface;

/**
 * @phpstan-import-type TJobDefinition from JobDefinition
 */
class JobDefinitionFactory implements JobDefinitionFactoryInterface
{
    protected JobLoaderInterface $defaultJobLoader;

    private QueueNameStrategy $queueNameStrategy;


    public function __construct(JobLoaderInterface $defaultJobLoader, ?QueueNameStrategy $queueNameStrategy = null)
    {
        $this->defaultJobLoader = $defaultJobLoader;
        $this->queueNameStrategy = $queueNameStrategy ?? PrefixedQueueNameStrategy::createDefault();
    }


    /**
     * @param TJobDefinition $jobDefinition
     */
    public function create(string $jobName, array $jobDefinition): JobDefinitionInterface
    {
        $queueName = $this->queueNameStrategy->getQueueName($jobDefinition[self::QUEUE_NAME]);

        return new JobDefinition(
            $jobName,
            $jobDefinition[self::JOB_CLASS],
            $queueName,
            $jobDefinition[self::MAX_ATTEMPTS] ?? null,
            $jobDefinition[self::JOB_LOADER] ?? $this->defaultJobLoader,
            $jobDefinition[self::JOB_DELAY_RULE],
            $jobDefinition[self::JOB_PROCESSOR],
        );
    }
}
