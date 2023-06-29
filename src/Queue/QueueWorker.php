<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue;

use BE\QueueManagement\Jobs\JobDefinitions\PrefixedQueueNameStrategy;
use BE\QueueManagement\Jobs\JobDefinitions\QueueNameStrategy;

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

    private QueueNameStrategy $queueNameStrategy;


    public function __construct(QueueManagerInterface $queueManager, callable $consumer, ?QueueNameStrategy $queueNameStrategy = null)
    {
        $this->queueManager = $queueManager;
        $this->consumer = $consumer;
        $this->queueNameStrategy = $queueNameStrategy ?? PrefixedQueueNameStrategy::createDefault();
    }


    /**
     * @param mixed[] $parameters
     */
    public function start(string $queueName, array $parameters = []): void
    {
        $queueName = $this->queueNameStrategy->getQueueName($queueName);

        $this->queueManager->consumeMessages($this->consumer, $queueName, $parameters);
    }


    public function terminateGracefully(): void
    {
        $this->queueManager->terminateGracefully();
    }
}
