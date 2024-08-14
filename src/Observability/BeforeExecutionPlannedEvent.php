<?php declare(strict_types = 1);

namespace BE\QueueManagement\Observability;

use BE\QueueManagement\Jobs\JobInterface;
use Ramsey\Uuid\UuidInterface;

class BeforeExecutionPlannedEvent
{
    /**
     * @param UuidInterface $executionPlannedId Same id is used for BeforeExecutionPlannedEvent and AfterExecutionPlannedEvent
     */
    public function __construct(
        public readonly UuidInterface $executionPlannedId,
        public readonly JobInterface $job,
        public readonly string $prefixedQueueName,
        public readonly int $delayInSeconds,
        public readonly PlannedExecutionStrategyEnum $plannedExecutionStrategy,
    ) {
    }
}
