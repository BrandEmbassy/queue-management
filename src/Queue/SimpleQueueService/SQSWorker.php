<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\SimpleQueueService;

use BE\QueueManagement\Queue\WorkerInterface;

final class SQSWorker implements WorkerInterface
{
    /**
     * @var SQSQueueManager
     */
    private $queueManager;

    /**
     * @var SQSConsumer
     */
    private $consumer;


    public function __construct(SQSQueueManager $sqsQueueManager, SQSConsumer $sqsConsumer)
    {
        $this->queueManager = $sqsQueueManager;
        $this->consumer = $sqsConsumer;
    }


    public function start(string $queueName, array $parameters = []): void
    {
        $this->queueManager->consumeMessages($this->consumer, $queueName, $parameters);
    }
}
