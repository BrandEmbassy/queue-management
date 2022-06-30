<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS\MessageDeduplication;

use BE\QueueManagement\Queue\AWSSQS\SqsMessage;

interface MessageDeduplication
{
    public function isDuplicate(SqsMessage $message): bool;
}
