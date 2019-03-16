<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Queue\WorkerInterface;

class RabbitMQWorker implements WorkerInterface
{
    /**
     * @var RabbitMQQueueManager
     */
    private $queueManager;

    /**
     * @var RabbitMQConsumer
     */
    private $consumer;


    public function __construct(RabbitMQQueueManager $queueManager, RabbitMQConsumer $consumer)
    {
        $this->queueManager = $queueManager;
        $this->consumer = $consumer;
    }


    public function start(string $queueName): void
    {
        $this->queueManager->consumeMessages($this->consumer, $queueName);
    }
}
