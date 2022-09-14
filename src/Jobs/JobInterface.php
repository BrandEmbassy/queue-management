<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use DateTimeImmutable;

interface JobInterface
{
    public const INIT_ATTEMPTS = 1;


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


    /**
     * @see \BE\QueueManagement\Jobs\HasExecutionPlannedAt
     */
    public function getExecutionPlannedAt(): ?DateTimeImmutable;


    /**
     * @see \BE\QueueManagement\Jobs\HasExecutionPlannedAt
     */
    public function executionPlanned(DateTimeImmutable $executionPlannedAt): void;
}
