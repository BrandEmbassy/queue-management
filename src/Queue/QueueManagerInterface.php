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


    /**
     * @param int $maxDelayInSeconds This parameter can be used to override the default maximum delay before using
     *                               delayed job scheduler (if one is configured). This can be useful for
     *                               implementation of automated tests & synthetic monitoring of delayed job
     *                               scheduler on live environments while maintaining quick feedback loop.
     */
    public function pushDelayed(JobInterface $job, int $delayInSeconds, ?int $maxDelayInSeconds = null): void;


    public function pushDelayedWithMilliseconds(JobInterface $job, int $delayInMilliseconds): void;


    public function checkConnection(): bool;


    public function terminateGracefully(): void;
}
