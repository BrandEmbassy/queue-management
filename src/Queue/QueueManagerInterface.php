<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue;

use BE\QueueManagement\Jobs\JobInterface;

interface QueueManagerInterface
{
    /**
     * @param mixed[] $parameters
     */
    public function consumeMessages(callable $consumer, string $queueName, array $parameters = []): void;


    public function push(JobInterface $job, int $delayInMilliseconds = 0, ?string $queueName = null): void;


    public function checkConnection(): bool;
}
