<?php declare(strict_types = 1);

namespace BE\QueueManagement\Observability;

enum PlannedExecutionStrategyEnum: string
{
    case SQS_DELIVERY_DELAY = 'SQS_DELIVERY_DELAY';

    case DELAYED_JOB_SCHEDULER = 'DELAYED_JOB_SCHEDULER';
}
