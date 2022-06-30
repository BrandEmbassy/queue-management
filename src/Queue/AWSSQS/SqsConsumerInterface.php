<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

interface SqsConsumerInterface
{
    public function __invoke(SqsMessage $message): void;
}
