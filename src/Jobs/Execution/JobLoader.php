<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\BlacklistedJobUuidException;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionsContainer;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\JobParameters;
use BE\QueueManagement\Jobs\JobTerminator;
use BrandEmbassy\DateTime\DateTimeFromString;
use Doctrine\Common\Collections\ArrayCollection;
use Nette\Utils\Json;

class JobLoader implements JobLoaderInterface
{
    private JobDefinitionsContainer $jobDefinitionsContainer;

    private JobTerminator $jobTerminator;


    public function __construct(JobDefinitionsContainer $jobDefinitionsContainer, JobTerminator $jobTerminator)
    {
        $this->jobDefinitionsContainer = $jobDefinitionsContainer;
        $this->jobTerminator = $jobTerminator;
    }


    public function loadJob(string $messageBody): JobInterface
    {
        $messageParameters = Json::decode($messageBody, Json::FORCE_ARRAY);

        $jobUuid = $messageParameters[JobParameters::UUID];
        $attempts = $messageParameters[JobParameters::ATTEMPTS];
        $executionPlannedAt = $messageParameters[JobParameters::EXECUTION_PLANNED_AT];

        $this->checkUuidBlacklist($jobUuid, $attempts);

        $jobDefinition = $this->jobDefinitionsContainer->get($messageParameters[JobParameters::JOB_NAME]);

        $jobLoader = $jobDefinition->getJobLoader();

        $job = $jobLoader->load(
            $jobDefinition,
            $jobUuid,
            DateTimeFromString::create($messageParameters[JobParameters::CREATED_AT]),
            $attempts,
            new ArrayCollection($messageParameters[JobParameters::PARAMETERS]),
        );

        $job->setExecutionPlannedAt($executionPlannedAt);

        return $job;
    }


    protected function checkUuidBlacklist(string $jobUuid, int $attempts): void
    {
        if ($this->jobTerminator->shouldBeTerminated($jobUuid, $attempts)) {
            $this->jobTerminator->terminate($jobUuid);

            throw BlacklistedJobUuidException::createFromJobUuid($jobUuid);
        }
    }
}
