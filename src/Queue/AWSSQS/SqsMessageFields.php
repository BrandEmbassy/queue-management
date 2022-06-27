<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

/**
 * @final
 */
class SqsMessageFields
{
    public const MESSAGE_BODY = 'MessageBody'; // for sending only. when receiving message actual attr name is 'Body'
    public const BODY = 'Body';
    public const DELAY_SECONDS = 'DelaySeconds'; // for sending only.
    public const QUEUE_URL = 'QueueUrl'; // for sending only.
    public const MESSAGE_ATTRIBUTES = 'MessageAttributes'; // for sending only. for reading use ATTRIBUTES
    public const ATTRIBUTES = 'Attributes';
    public const RECEIPT_HANDLE = 'ReceiptHandle';
    public const MESSAGE_ID = 'MessageId';
}
