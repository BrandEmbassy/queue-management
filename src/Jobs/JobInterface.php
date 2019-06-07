<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use DateTimeImmutable;

interface JobInterface
{
    public const UUID = 'jobUuid';
    public const JOB_NAME = 'jobName';
    public const ATTEMPTS = 'attempts';
    public const CREATED_AT = 'createdAt';
    public const PARAMETERS = 'jobParameters';


    public function getUuid(): string;


    public function getName(): string;


    public function getAttempts(): int;


    public function getMaxAttempts(): ?int;


    /**
     * @param mixed[] $customParameters
     */
    public function toJson(array $customParameters = []): string;


    /**
     * @return mixed
     */
    public function getParameter(string $key);


    public function getCreatedAt(): DateTimeImmutable;


    public function getExecutionStartedAt(): ?DateTimeImmutable;


    public function executionStarted(DateTimeImmutable $startedAt): void;


    public function incrementAttempts(): void;


    public function getJobDefinition(): JobDefinitionInterface;
}
