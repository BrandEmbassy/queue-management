<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\S3\S3Client;

interface S3ClientFactoryInterface
{
    public function create(): S3Client;
}
