<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use RuntimeException;
use Throwable;

class JobValidationException extends RuntimeException
{
    /**
     * @var JobInterface
     */
    private $job;


    public function __construct(string $message, JobInterface $job, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->job = $job;
    }


    public function getJob(): JobInterface
    {
        return $this->job;
    }
}
