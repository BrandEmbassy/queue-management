<?php declare(strict_types = 1);

namespace BE\QueueExample\Sqs;

use BE\QueueManagement\Queue\AWSSQS\SqsQueueManager;
use BE\QueueManagement\Queue\WorkerInterface;

class CustomSqsWorker
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
            'https://sqs.eu-central-1.amazonaws.com/583027634990/MyQueue1',
            [
                SqsQueueManager::MAX_NUMBER_OF_MESSAGES => 1
            ]
        );
        
        echo "SQS Worker started!";
    }
}