<?php declare(strict_types = 1);

namespace BE\QueueManagement\Observability;

class MessageSentEvent
{
    /**
     * @param mixed[] $messageAttributes
     */
    public function __construct(
        public readonly int $delayInSeconds,
        public readonly string $messageId,
        public readonly array $messageAttributes,
        public readonly string $messageBody,
    ) {
    }
}
