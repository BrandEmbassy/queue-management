<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving\FailResolveStrategy;

use BE\QueueManagement\Jobs\JobInterface;
use Throwable;

final class ConstantDelayFailResolveStrategy implements FailResolveStrategy
{
    /**
     * @var int
     */
    private $constantDelayInSeconds;


    public function __construct(int $constantDelayInSeconds)
    {
        $this->constantDelayInSeconds = $constantDelayInSeconds;
    }


    public function getDelayInMilliseconds(JobInterface $job, Throwable $exception): int
    {
        return $this->constantDelayInSeconds * 1000;
    }


    public function getQueueName(JobInterface $job, Throwable $exception): string
    {
        return $job->getJobDefinition()->getQueueName();
    }
}
