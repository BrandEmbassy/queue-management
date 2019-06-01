<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionsContainer;
use BE\QueueManagement\Jobs\JobInterface;
use BrandEmbassy\DateTime\DateTimeFromString;
use DateTime;
use Nette\Utils\Json;

class JobLoader implements JobLoaderInterface
{
    /**
     * @var JobDefinitionsContainer
     */
    private $jobDefinitionsContainer;


    public function __construct(JobDefinitionsContainer $jobDefinitionsContainer)
    {
        $this->jobDefinitionsContainer = $jobDefinitionsContainer;
    }


    public function loadJob(string $messageBody): JobInterface
    {
        $messageParameters = Json::decode($messageBody, Json::FORCE_ARRAY);

        $jobDefinition = $this->jobDefinitionsContainer->get($messageParameters[JobInterface::JOB_NAME]);

        $jobLoader = $jobDefinition->getJobLoader();

        return $jobLoader->load(
            $jobDefinition,
            $messageParameters[JobInterface::UUID],
            DateTimeFromString::create(
                DateTime::ATOM,
                $messageParameters[JobInterface::CREATED_AT]
            ),
            $messageParameters[JobInterface::ATTEMPTS],
            $messageParameters[JobInterface::PARAMETERS]
        );
    }
}
