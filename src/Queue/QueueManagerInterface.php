<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue;

use BE\QueueManagement\Jobs\JobInterface;

interface QueueManagerInterface
{
    /**
     * @param mixed[] $parameters
     */
    public function consumeMessages(callable $consumer, string $queueName, array $parameters = []): void;


    public function push(JobInterface $job): void;


    public function pushDelayed(JobInterface $job, int $delayInSeconds): void;


    public function pushDelayedWithMilliseconds(JobInterface $job, int $delayInMilliseconds): void;


    public function checkConnection(): bool;


    public function terminateGracefully(): void;
}
