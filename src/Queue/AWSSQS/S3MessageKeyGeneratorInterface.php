<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

interface S3MessageKeyGeneratorInterface
{
    public function generate(): string;
}
