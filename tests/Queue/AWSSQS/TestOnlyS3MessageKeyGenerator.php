<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Queue\AWSSQS\S3MessageKeyGeneratorInterface;

/**
 * @final
 */
class TestOnlyS3MessageKeyGenerator implements S3MessageKeyGeneratorInterface
{
    public const S3_KEY = '/sqsQueueJobs/jobUuid.json';


    public function generate(): string
    {
        return self::S3_KEY;
    }
}
