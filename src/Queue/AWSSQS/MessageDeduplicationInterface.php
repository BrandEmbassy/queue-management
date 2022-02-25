<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

interface MessageDeduplicationInterface {
    public function isDuplicate(SqsMessage $message): bool;
}