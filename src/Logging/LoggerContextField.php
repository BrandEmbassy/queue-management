<?php declare(strict_types = 1);

namespace BE\QueueManagement\Logging;

/**
 * @final
 */
class LoggerContextField
{
    public const EXCEPTION = 'exception';
    public const PREVIOUS_EXCEPTION = 'previousException';
    public const JOB_EXECUTION_TIME = 'executionTime';
    public const JOB_NAME = 'job_name';
    public const JOB_UUID = 'job_uuid';
    public const JOB_QUEUE_NAME = 'job_queue_name';
    public const MESSAGE_BODY = 'message_body';
    public const MESSAGE_ID = 'message_id';
}
