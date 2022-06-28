<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Nette\StaticClass;

/**
 * @final
 */
class SqsMessageFields
{
    use StaticClass;

    public const MESSAGE_ATTRIBUTES = 'MessageAttributes';
    public const BODY = 'Body';
    public const ATTRIBUTES = 'Attributes';
    public const RECEIPT_HANDLE = 'ReceiptHandle';
    public const MESSAGE_ID = 'MessageId';
    public const QUEUE_URL = 'QueueUrl';
}
