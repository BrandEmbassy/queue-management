<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Queue\JobExecutionStatus;

interface SqsConsumerInterface
{
    public function __invoke(SqsMessage $message): JobExecutionStatus;
}
