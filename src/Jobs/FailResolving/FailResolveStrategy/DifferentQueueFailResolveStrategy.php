<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving\FailResolveStrategy;

use BE\QueueManagement\Jobs\JobInterface;
use Throwable;

final class DifferentQueueFailResolveStrategy implements FailResolveStrategy
{
    /**
     * @var string
     */
    private $newQueueName;


    public function __construct(string $newQueueName)
    {
        $this->newQueueName = $newQueueName;
    }


    public function getDelayInMilliseconds(JobInterface $job, Throwable $exception): int
    {
        return 0;
    }


    public function getTargetQueueName(JobInterface $job, Throwable $exception): string
    {
        return $this->newQueueName;
    }
}
