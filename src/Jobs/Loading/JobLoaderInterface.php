<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Loading;

use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use BE\QueueManagement\Jobs\JobInterface;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;

interface JobLoaderInterface
{
    /**
     * @param Collection<string, mixed>|mixed[] $parameters
     */
    public function load(
        JobDefinitionInterface $jobDefinition,
        string $uuid,
        DateTimeImmutable $createdAt,
        int $attempts,
        Collection $parameters
    ): JobInterface;
}
