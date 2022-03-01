<?php declare(strict_types = 1);

namespace BE\QueueExample\Rabbit;

use BE\QueueManagement\Queue\RabbitMQ\RabbitMQQueueManager;
use BE\QueueManagement\Queue\WorkerInterface;

class Worker
{
    /**
     * @var WorkerInterface
     */
    private $worker;


    public function __construct(WorkerInterface $worker)
    {
        $this->worker = $worker;
    }


    public function startWorker(): void
    {
        $this->worker->start(
            'example_queue',
            [
                RabbitMQQueueManager::PREFETCH_COUNT => 1,
                RabbitMQQueueManager::NO_ACK => false,
            ]
        );
        
        echo "Worker started";
    }
}