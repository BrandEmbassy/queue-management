<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

use BE\QueueManagement\Jobs\Execution\JobProcessorInterface;
use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleInterface;
use BE\QueueManagement\Jobs\Loading\JobLoaderInterface;

class JobDefinition implements JobDefinitionInterface
{
    private string $jobClass;

    private string $queueName;

    private ?string $s3BucketName;

    private ?int $maxAttempts;

    private JobLoaderInterface $jobLoader;

    private DelayRuleInterface $delayRule;

    private JobProcessorInterface $jobProcessor;

    private string $jobName;


    public function __construct(
        string $jobName,
        string $jobClass,
        string $queueName,
        ?string $s3BucketName,
        ?int $maxAttempts,
        JobLoaderInterface $jobLoader,
        DelayRuleInterface $delayRule,
        JobProcessorInterface $jobProcessor
    ) {
        $this->jobName = $jobName;
        $this->jobClass = $jobClass;
        $this->queueName = $queueName;
        $this->s3BucketName = $s3BucketName;
        $this->maxAttempts = $maxAttempts;
        $this->jobLoader = $jobLoader;
        $this->delayRule = $delayRule;
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


    public function getS3BucketName(): ?string
    {
        return $this->s3BucketName;
    }


    public function getMaxAttempts(): ?int
    {
        return $this->maxAttempts;
    }


    public function getJobLoader(): JobLoaderInterface
    {
        return $this->jobLoader;
    }


    public function getDelayRule(): DelayRuleInterface
    {
        return $this->delayRule;
    }


    public function getJobProcessor(): JobProcessorInterface
    {
        return $this->jobProcessor;
    }
}
