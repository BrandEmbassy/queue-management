<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use DateTimeImmutable;

interface JobInterface
{
    public const UUID = 'uuid';
    public const JOB_NAME = 'jobName';
    public const JOB_CLASS = 'jobClass';
    public const ATTEMPTS = 'attempts';
    public const PARAMETERS = 'parameters';


    public function getUuid(): string;


    public function getName(): string;


    public function getAttempts(): int;


    public function toJson(): string;


    /**
     * @param mixed $value
     */
    public function setParameter(string $key, $value): void;


    /**
     * @return mixed
     */
    public function getParameter(string $key);


    public function getQueueName(): string;


    public function getMaxAttempts(): ?int;


    public function getExecutionStartedAt(): ?DateTimeImmutable;


    public function executionStarted(DateTimeImmutable $startedAt): void;


    public function incrementAttempts(): void;
}
