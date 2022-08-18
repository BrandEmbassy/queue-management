<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue;

/**
 * @final
 */
class QueueWorker implements WorkerInterface
{
    private QueueManagerInterface $queueManager;

    /**
     * @var callable
     */
    private $consumer;


    public function __construct(QueueManagerInterface $queueManager, callable $consumer)
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
