<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\JobDefinitions;

use BE\QueueManagement\Jobs\Execution\JobProcessorInterface;
use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleInterface;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use BE\QueueManagement\Jobs\Loading\JobLoaderInterface;
use RuntimeException;
use Tests\BE\QueueManagement\Jobs\ExampleJob;

/**
 * @final
 */
class ExampleJobDefinition implements JobDefinitionInterface
{
    public const QUEUE_NAME = 'exampleJobQueue';
    public const S3_BUCKET_NAME = 'exampleJobS3Bucket';
    public const MAX_ATTEMPTS = 3;

    private ?JobLoaderInterface $jobLoader = null;

    private string $jobName;

    private string $jobClass;

    private ?DelayRuleInterface $delayRule = null;

    private ?JobProcessorInterface $jobProcessor = null;


    public function __construct(string $jobName, string $jobClass)
    {
        $this->jobName = $jobName;
        $this->jobClass = $jobClass;
    }


    public static function create(string $jobName = ExampleJob::JOB_NAME, string $jobClass = ExampleJob::class): self
    {
        return new self($jobName, $jobClass);
    }


    public function withJobLoader(JobLoaderInterface $jobLoader): self
    {
        $clone = clone $this;
        $clone->jobLoader = $jobLoader;

        return $clone;
    }


    public function withDelayRule(DelayRuleInterface $delayRule): self
    {
        $clone = clone $this;
        $clone->delayRule = $delayRule;

        return $clone;
    }


    public function withJobProcessor(JobProcessorInterface $jobProcessor): self
    {
        $clone = clone $this;
        $clone->jobProcessor = $jobProcessor;

        return $clone;
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
        return self::QUEUE_NAME;
    }


    public function getS3BucketName(): ?string
    {
        return null;
    }


    public function getMaxAttempts(): ?int
    {
        return self::MAX_ATTEMPTS;
    }


    public function getJobLoader(): JobLoaderInterface
    {
        if ($this->jobLoader !== null) {
            return $this->jobLoader;
        }

        throw new RuntimeException('Not implemented');
    }


    public function getDelayRule(): DelayRuleInterface
    {
        if ($this->delayRule !== null) {
            return $this->delayRule;
        }

        throw new RuntimeException('Not implemented');
    }


    public function getJobProcessor(): JobProcessorInterface
    {
        if ($this->jobProcessor !== null) {
            return $this->jobProcessor;
        }

        throw new RuntimeException('Not implemented');
    }
}
