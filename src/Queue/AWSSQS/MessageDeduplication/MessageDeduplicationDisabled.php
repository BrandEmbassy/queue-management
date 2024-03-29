<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS\MessageDeduplication;

use BE\QueueManagement\Queue\AWSSQS\SqsMessage;

/**
 * If message deduplication is not needed this implementation of deduplication should be used.
 * It allows all messages to be processed and no message ever is marked as duplicate
 *
 * @final
 */
class MessageDeduplicationDisabled implements MessageDeduplication
{
    public function isDuplicate(SqsMessage $message): bool
    {
        return false;
    }
}
