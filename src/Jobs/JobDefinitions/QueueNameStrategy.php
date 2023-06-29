<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

interface QueueNameStrategy
{
    public function getQueueName(string $queueName): string;
}
