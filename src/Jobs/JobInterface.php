<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttribute;
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
     *
     * @return mixed[]
     */
    public function toArray(array $customParameters = []): array;


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


    public function getExecutionPlannedAt(): ?DateTimeImmutable;


    public function setExecutionPlannedAt(DateTimeImmutable $executionPlannedAt): void;


    /**
     * @return array<string,SqsMessageAttribute>
     */
    public function getMessageAttributes(): array;


    /**
     * @param array<string,SqsMessageAttribute> $messageAttributes
     */
    public function setMessageAttributes(array $messageAttributes): void;


    public function getMessageAttribute(
        string $messageAttributeName,
    ): SqsMessageAttribute|null;


    public function setMessageAttribute(
        SqsMessageAttribute $sqsMessageAttribute,
    ): void;
}
