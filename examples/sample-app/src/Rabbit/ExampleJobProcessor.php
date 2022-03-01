<?php declare(strict_types = 1);

namespace BE\QueueExample\Rabbit;

use BE\QueueManagement\Jobs\Execution\JobProcessorInterface;
use BE\QueueManagement\Jobs\JobInterface;

class ExampleJobProcessor implements JobProcessorInterface
{
    public function process(JobInterface $job): void
    {
        assert($job instanceof ExampleJob);
        
        echo $job->getFoo();
        echo $job->toJson();
        echo $job->getParameter('foo');
    }
}