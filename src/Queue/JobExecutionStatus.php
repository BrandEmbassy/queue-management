<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue;

enum JobExecutionStatus
{
    case SUCCESS;
    case FAILED_DELAYED;
    case FAILED_UNRESOLVABLE;
    case REMOVED_DUPLICATE;
    case DELAYED_NOT_PLANNED_YET;
}
