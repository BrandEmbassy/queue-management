<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue;

interface WorkerInterface
{
    /**
     * @param mixed[] $parameters
     */
    public function start(string $queueName, array $parameters = []): void;


    public function terminateGracefully(): void;
}
