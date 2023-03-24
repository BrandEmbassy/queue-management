<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Jobs\JobInterface;

interface MessageKeyGeneratorInterface
{
    public function generate(JobInterface $job): string;
}
