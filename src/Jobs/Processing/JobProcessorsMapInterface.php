<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Processing;

interface JobProcessorsMapInterface
{
    public function getJobProcessor(string $jobName): JobProcessorInterface;
}
