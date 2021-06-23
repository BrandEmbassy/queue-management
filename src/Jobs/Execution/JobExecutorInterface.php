<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\JobInterface;

interface JobExecutorInterface
{
    /**
     * @throws ConsumerFailedExceptionInterface
     * @throws UnresolvableProcessFailExceptionInterface
     */
    public function execute(JobInterface $job): void;
}
