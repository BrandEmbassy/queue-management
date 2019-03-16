<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue;

interface WorkerInterface
{
    public function start(string $queueName): void;
}
