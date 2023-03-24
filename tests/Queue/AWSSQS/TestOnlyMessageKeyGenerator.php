<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Queue\AWSSQS\MessageKeyGeneratorInterface;

/**
 * @final
 */
class TestOnlyMessageKeyGenerator implements MessageKeyGeneratorInterface
{
    public const S3_KEY = '/sqsQueueJobs/jobUuid.json';


    public function generate(JobInterface $job): string
    {
        return self::S3_KEY;
    }
}
