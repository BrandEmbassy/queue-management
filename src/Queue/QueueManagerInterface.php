<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue;

use BE\QueueManagement\Jobs\JobInterface;

interface QueueManagerInterface
{
    public function consumeMessages(callable $consumer, string $queueName): void;


    public function push(JobInterface $job): void;


    public function pushDelayed(JobInterface $job, int $delay): void;
}
