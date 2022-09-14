<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\JobInterface;
use RuntimeException;

class SqsJobDelayException extends RuntimeException
{
    private JobInterface $job;

    private int $delayInSeconds;


    public function __construct(JobInterface $job, int $delayInSeconds)
    {
        parent::__construct();
        $this->job = $job;
        $this->delayInSeconds = $delayInSeconds;
    }


    public function getJob(): JobInterface
    {
        return $this->job;
    }


    public function getDelayInSeconds(): int
    {
        return $this->delayInSeconds;
    }
}
