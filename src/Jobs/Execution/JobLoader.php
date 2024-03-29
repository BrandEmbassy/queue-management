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

/**
 * @phpstan-import-type TJobParameters from JobParameters
 */
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
        /** @var TJobParameters $messageParameters */
        $messageParameters = Json::decode($messageBody, Json::FORCE_ARRAY);

        $jobUuid = $messageParameters[JobParameters::UUID];
        $attempts = $messageParameters[JobParameters::ATTEMPTS];
        $executionPlannedAt = $messageParameters[JobParameters::EXECUTION_PLANNED_AT] ?? null;

        $this->checkUuidBlacklist($jobUuid, $attempts);

        $jobDefinition = $this->jobDefinitionsContainer->get($messageParameters[JobParameters::JOB_NAME]);

        $jobLoader = $jobDefinition->getJobLoader();

        $executionPlannedAtDateTime = $executionPlannedAt !== null
            ? DateTimeFromString::create($executionPlannedAt)
            : null;

        return $jobLoader->load(
            $jobDefinition,
            $jobUuid,
            DateTimeFromString::create($messageParameters[JobParameters::CREATED_AT]),
            $attempts,
            new ArrayCollection($messageParameters[JobParameters::PARAMETERS]),
            $executionPlannedAtDateTime,
        );
    }


    protected function checkUuidBlacklist(string $jobUuid, int $attempts): void
    {
        if ($this->jobTerminator->shouldBeTerminated($jobUuid, $attempts)) {
            $this->jobTerminator->terminate($jobUuid);

            throw BlacklistedJobUuidException::createFromJobUuid($jobUuid);
        }
    }
}
