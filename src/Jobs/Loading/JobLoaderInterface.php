<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Loading;

use BE\QueueManagement\Jobs\JobInterface;
use Doctrine\Common\Collections\Collection;

interface JobLoaderInterface
{
    /**
     * @param Collection|mixed[] $parameters
     *
     * @return JobInterface
     */
    public function load(string $uuid, string $jobName, int $attempts, Collection $parameters): JobInterface;
}
