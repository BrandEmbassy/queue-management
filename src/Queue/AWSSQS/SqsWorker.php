<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Queue\QueueManagerInterface;
use BE\QueueManagement\Queue\WorkerInterface;

class SqsWorker implements WorkerInterface
{
    /**
     * @var QueueManagerInterface
     */
    private $queueManager;

    /**
     * @var SqsConsumerInterface
     */
    private $consumer;


    public function __construct(QueueManagerInterface $queueManager, SqsConsumerInterface $consumer)
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