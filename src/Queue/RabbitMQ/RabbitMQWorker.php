<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Queue\WorkerInterface;

/**
 * @final
 */
class RabbitMQWorker implements WorkerInterface
{
    private RabbitMQQueueManager $rabbitMQQueueManager;

    private RabbitMQConsumerInterface $consumer;


    public function __construct(RabbitMQQueueManager $rabbitMQQueueManager, RabbitMQConsumerInterface $consumer)
    {
        $this->rabbitMQQueueManager = $rabbitMQQueueManager;
        $this->consumer = $consumer;
    }


    /**
     * @param mixed[] $parameters
     */
    public function start(string $queueName, array $parameters = []): void
    {
        $this->rabbitMQQueueManager->consumeMessages($this->consumer, $queueName, $parameters);
    }
}
