<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Loading;

use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\SimpleJob;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use function assert;
use function is_a;

class SimpleJobLoader implements JobLoaderInterface
{
    /**
     * @param Collection<string, mixed> $parameters
     */
    public function load(
        JobDefinitionInterface $jobDefinition,
        string $uuid,
        DateTimeImmutable $createdAt,
        int $attempts,
        Collection $parameters,
        ?DateTimeImmutable $executionPlannedAt = null
    ): JobInterface {
        $jobClass = $jobDefinition->getJobClass();
        assert(is_a($jobClass, SimpleJob::class, true), 'Loaded job class must be instance of ' . SimpleJob::class);

        return new $jobClass($uuid, $createdAt, $attempts, $jobDefinition, $parameters, $executionPlannedAt);
    }
}
