<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use MabeEnum\Enum;

/**
 * @final
 */
class JobType extends Enum
{
    public const SQS = 'sqs';
    public const RABBIT_MQ = 'rabbitMq';
    public const UNKNOWN = 'unknown';
}
