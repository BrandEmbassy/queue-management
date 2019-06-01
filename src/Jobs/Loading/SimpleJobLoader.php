<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Loading;

use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\SimpleJob;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;

class SimpleJobLoader implements JobLoaderInterface
{
    /**
     * @param Collection|mixed[] $parameters
     *
     * @return JobInterface
     */
    public function load(
        JobDefinitionInterface $jobDefinition,
        string $uuid,
        DateTimeImmutable $createdAt,
        int $attempts,
        Collection $parameters
    ): JobInterface {
        /** @var SimpleJob $jobClass */
        $jobClass = $jobDefinition->getJobClass();

        return new $jobClass($uuid, $createdAt, $attempts, $jobDefinition);
    }
}
