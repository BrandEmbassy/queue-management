<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Queue\QueueManagerInterface;
use BE\QueueManagement\Queue\WorkerInterface;

/**
 * @final
 */
class RabbitMQWorker implements WorkerInterface
{
    private QueueManagerInterface $queueManager;

    private RabbitMQConsumerInterface $consumer;


    public function __construct(QueueManagerInterface $queueManager, RabbitMQConsumerInterface $consumer)
    {
        $this->queueManager = $queueManager;
        $this->consumer = $consumer;
    }


    /**
     * @param mixed[] $parameters
     */
    public function start(string $queueName, array $parameters = []): void
    {
        $this->queueManager->consumeMessages($this->consumer, $queueName, $parameters);
    }
}
