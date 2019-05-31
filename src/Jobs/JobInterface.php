<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use DateTimeImmutable;

interface JobInterface
{
    public const UUID = 'jobUuid';
    public const JOB_NAME = 'jobName';
    public const JOB_CLASS = 'jobClass';
    public const ATTEMPTS = 'attempts';
    public const CREATED_AT = 'createdAt';
    public const PARAMETERS = 'jobParameters';


    public function getUuid(): string;


    public function getName(): string;


    public function getAttempts(): int;


    public function getMaxAttempts(): ?int;


    public function toJson(): string;


    /**
     * @return mixed
     */
    public function getParameter(string $key);


    public function getCreatedAt(): DateTimeImmutable;


    public function getExecutionStartedAt(): ?DateTimeImmutable;


    public function executionStarted(DateTimeImmutable $startedAt): void;


    public function incrementAttempts(): void;
}
