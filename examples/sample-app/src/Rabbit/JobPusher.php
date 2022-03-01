<?php declare(strict_types = 1);

namespace BE\QueueExample\Rabbit;

use BE\QueueManagement\Queue\QueueManagerInterface;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionsContainer;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

class JobPusher
{

    /**
     * @var QueueManagerInterface
     */
    protected $queueManager;

    /**
     * @var JobDefinitionsContainer
     */
    private $jobDefinitionsContainer;


    public function __construct(QueueManagerInterface $queueManager, JobDefinitionsContainer $jobDefinitionsContainer) {
        $this->queueManager = $queueManager;
        $this->jobDefinitionsContainer = $jobDefinitionsContainer;
    }
    

    public function push(string $jobUuid): void
    {
        $jobDefinition = $this->jobDefinitionsContainer->get(ExampleJob::JOB_NAME);

        $exampleJob = new ExampleJob(
            $jobUuid,
            new DateTimeImmutable(),
            JobInterface::INIT_ATTEMPTS,
            $jobDefinition,
            new ArrayCollection([ExampleJob::PARAMETER_FOO => 'bar'])
        );

        $this->queueManager->push($exampleJob);
    }
}