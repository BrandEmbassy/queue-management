<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Ramsey\Uuid\Uuid;

/**
 * @final
 */
class MessageKeyGenerator implements MessageKeyGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::uuid4()->toString() . '.json';
    }
}
