<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Ramsey\Uuid\Uuid;

/**
 * @final
 */
class S3MessageKeyGenerator implements S3MessageKeyGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::uuid4()->toString() . '.json';
    }
}
