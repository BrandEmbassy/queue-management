<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Queue\QueueManagerInterface;

class RabbitMQQueueManager implements QueueManagerInterface
{
    public function consumeMessages(callable $consumer, string $queueName): void
    {
        // TODO: Implement consumeMessages() method.
    }


    public function push(JobInterface $job): void
    {
        // TODO: Implement push() method.
    }


    public function pushDelayed(JobInterface $job, int $delay): void
    {
        // TODO: Implement pushDelayed() method.
    }
}
