<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

/**
 * @final
 */
class SqsMessageFields
{
    public const MESSAGEBODY = 'MessageBody'; // for sending only. when receiving message actual attr name is 'Body'
    public const BODY = 'Body';
    public const DELAYSECONDS = 'DelaySeconds'; // for sending only.
    public const QUEUEURL = 'QueueUrl'; // for sending only.
    public const MESSAGEATTRIBUTES = 'MessageAttributes'; // for sending only. for reading use ATTRIBUTES
    public const ATTRIBUTES = 'Attributes';
    public const RECEIPTHANDLE = 'ReceiptHandle';
    public const MESSAGEID = 'MessageId';
}
