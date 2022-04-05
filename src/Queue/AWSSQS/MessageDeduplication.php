<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

interface MessageDeduplication
{
    public function isDuplicate(SqsMessage $message): bool;
}
