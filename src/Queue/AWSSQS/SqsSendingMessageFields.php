<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Nette\StaticClass;

/**
 * @final
 */
class SqsSendingMessageFields
{
    use StaticClass;

    public const MESSAGE_BODY = 'MessageBody';

    public const DELAY_SECONDS = 'DelaySeconds';

    public const QUEUE_URL = 'QueueUrl';

    public const MESSAGE_ATTRIBUTES = 'MessageAttributes';
}
