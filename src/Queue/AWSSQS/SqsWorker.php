<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Queue\WorkerInterface;

/**
 * @final
 */
class SqsWorker implements WorkerInterface
{
    private SqsQueueManager $sqsQueueManager;

    private SqsConsumerInterface $consumer;


    public function __construct(SqsQueueManager $sqsQueueManager, SqsConsumerInterface $consumer)
    {
        $this->sqsQueueManager = $sqsQueueManager;
        $this->consumer = $consumer;
    }


    /**
     * @param mixed[] $parameters
     */
    public function start(string $queueName, array $parameters = []): void
    {
        $this->sqsQueueManager->consumeMessages($this->consumer, $queueName, $parameters);
    }
}
