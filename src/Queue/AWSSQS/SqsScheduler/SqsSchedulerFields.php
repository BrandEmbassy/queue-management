<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS\SqsScheduler;

use Nette\StaticClass;

/**
 * @final
 */
class SqsSchedulerFields
{
    use StaticClass;

    final public const EVENT_ID = 'eventId';

    final public const JOB_ID = 'jobId';

    final public const TENANT_ID = 'tenantId';

    final public const BRAND_ID = 'brandId';

    final public const CXONE_USER_ID = 'cxoneUserId';

    final public const USER_ID = 'userId';

    final public const DESTINATION_QUEUE_NAME = 'destinationQueueName';

    final public const DELIVERY_SCHEDULED_AT = 'deliveryScheduledAt';

    final public const REMAINING_RETRIES = 'remainingRetries';

    final public const DATA = 'data';
}
