<?php declare(strict_types = 1);

namespace BE\QueueManagement\Observability;

use BE\QueueManagement\Jobs\JobInterface;
use DateTimeImmutable;

class ExecutionPlannedEvent
{
    public function __construct(
        public readonly JobInterface $job,
        public readonly DateTimeImmutable $executionPlannedAt,
        public readonly int $delayInSeconds,
        public readonly string $prefixedQueueName,
        public readonly ?string $scheduledEventId,
    ) {
    }
}
