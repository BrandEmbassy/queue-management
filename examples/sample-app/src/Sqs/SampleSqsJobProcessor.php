<?php declare(strict_types = 1);

namespace BE\QueueExample\Sqs;

use BE\QueueManagement\Jobs\Execution\JobProcessorInterface;
use BE\QueueManagement\Jobs\JobInterface;

class SampleSqsJobProcessor implements JobProcessorInterface
{
    public function process(JobInterface $job): void
    {
        assert($job instanceof SampleSqsJob);
        echo "SampleSqsJobProcessor called!\n";
        echo $job->getFoo();
        echo $job->toJson();
        echo $job->getParameter('foo');
    }
}