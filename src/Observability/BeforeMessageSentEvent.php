<?php declare(strict_types = 1);

namespace BE\QueueManagement\Observability;

use BE\QueueManagement\Jobs\JobInterface;

class BeforeMessageSentEvent
{
    public function __construct(
        public readonly JobInterface $job,
        public readonly int $delayInSeconds,
        public readonly string $prefixedQueueName,
    ) {
    }
}
