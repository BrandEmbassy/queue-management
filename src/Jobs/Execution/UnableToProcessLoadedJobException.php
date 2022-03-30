<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\JobInterface;
use RuntimeException;
use Throwable;

class UnableToProcessLoadedJobException extends RuntimeException implements DelayableProcessFailExceptionInterface
{
    private JobInterface $job;


    public function __construct(JobInterface $job, string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->job = $job;
    }


    public function getJob(): JobInterface
    {
        return $this->job;
    }
}
