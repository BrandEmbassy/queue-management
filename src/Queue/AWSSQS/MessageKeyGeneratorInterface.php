<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

interface MessageKeyGeneratorInterface
{
    public function generate(): string;
}
