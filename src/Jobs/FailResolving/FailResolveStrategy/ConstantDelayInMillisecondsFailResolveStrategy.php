<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving\FailResolveStrategy;

use BE\QueueManagement\Jobs\JobInterface;
use Throwable;

final class ConstantDelayInMillisecondsFailResolveStrategy implements FailResolveStrategy
{
    /**
     * @var int
     */
    private $constantDelayInMilliseconds;


    public function __construct(int $constantDelayInMilliseconds)
    {
        $this->constantDelayInMilliseconds = $constantDelayInMilliseconds;
    }


    public function getDelayInMilliseconds(JobInterface $job, Throwable $exception): int
    {
        return $this->constantDelayInMilliseconds;
    }


    public function getTargetQueueName(JobInterface $job, Throwable $exception): string
    {
        return $job->getJobDefinition()->getQueueName();
    }
}
