<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use MabeEnum\Enum;

/**
 * @final
 */
class JobType extends Enum
{
    public const SQS = 'SQS';
    public const UNKNOWN = 'unknown';
}
