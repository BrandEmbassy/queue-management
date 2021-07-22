<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

use BE\QueueManagement\Jobs\Execution\JobProcessorInterface;
use BE\QueueManagement\Jobs\FailResolving\FailResolveStrategy\FailResolveStrategy;
use BE\QueueManagement\Jobs\Loading\JobLoaderInterface;

class JobDefinition implements JobDefinitionInterface
{
    /**
     * @var string
     */
    private $jobClass;

    /**
     * @var string
     */
    private $queueName;

    /**
     * @var int|null
     */
    private $maxAttempts;

    /**
     * @var JobLoaderInterface
     */
    private $jobLoader;

    /**
     * @var FailResolveStrategy
     */
    private $resolveStrategy;

    /**
     * @var JobProcessorInterface
     */
    private $jobProcessor;

    /**
     * @var string
     */
    private $jobName;


    public function __construct(
        string $jobName,
        string $jobClass,
        string $queueName,
        ?int $maxAttempts,
        JobLoaderInterface $jobLoader,
        FailResolveStrategy $resolveStrategy,
        JobProcessorInterface $jobProcessor
    ) {
        $this->jobName = $jobName;
        $this->jobClass = $jobClass;
        $this->queueName = $queueName;
        $this->maxAttempts = $maxAttempts;
        $this->jobLoader = $jobLoader;
        $this->resolveStrategy = $resolveStrategy;
        $this->jobProcessor = $jobProcessor;
    }


    public function getJobName(): string
    {
        return $this->jobName;
    }


    public function getJobClass(): string
    {
        return $this->jobClass;
    }


    public function getQueueName(): string
    {
        return $this->queueName;
    }


    public function getMaxAttempts(): ?int
    {
        return $this->maxAttempts;
    }


    public function getJobLoader(): JobLoaderInterface
    {
        return $this->jobLoader;
    }


    public function getFailResolveStrategy(): FailResolveStrategy
    {
        return $this->resolveStrategy;
    }


    public function getJobProcessor(): JobProcessorInterface
    {
        return $this->jobProcessor;
    }
}
