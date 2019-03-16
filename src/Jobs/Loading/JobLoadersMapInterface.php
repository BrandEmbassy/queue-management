<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Loading;

interface JobLoadersMapInterface
{
    public function getJobLoader(string $jobName): JobLoaderInterface;
}
