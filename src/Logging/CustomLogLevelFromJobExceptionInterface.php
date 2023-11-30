<?php declare(strict_types = 1);

namespace BE\QueueManagement\Logging;

use BE\QueueManagement\Jobs\JobInterface;
use Psr\Log\LogLevel;

interface CustomLogLevelFromJobExceptionInterface
{
    /**
     * @return LogLevel::*
     */
    public function getLogLevelForJob(JobInterface $job): string;
}
