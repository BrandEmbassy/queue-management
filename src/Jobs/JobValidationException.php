<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use RuntimeException;
use Throwable;
use function implode;
use function sprintf;

class JobValidationException extends RuntimeException
{
    private JobInterface $job;


    public function __construct(string $message, JobInterface $job, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->job = $job;
    }


    public function getJob(): JobInterface
    {
        return $this->job;
    }


    /**
     * @param mixed[] $existingKeys
     */
    public static function createFromUnknownParameter(string $key, array $existingKeys, JobInterface $job): self
    {
        return new self(
            sprintf(
                'Parameter %s not found, available parameters: %s',
                $key,
                implode(', ', $existingKeys),
            ),
            $job,
        );
    }
}
