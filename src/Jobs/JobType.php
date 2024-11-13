<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

enum JobType: string
{
    case SQS = 'SQS';
    case UNKNOWN = 'unknown';
}
