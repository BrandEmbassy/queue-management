<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use function strlen;

/**
 * Represent SQS Message
 *
 * AWS SQS API does not provide type for SQSMessage, only type \Aws\Result. This class is simple abstraction over this generic type.
 * For details see https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.Result.html
 *
 * @final
 */
class SqsMessage
{
    /** The maximum size that SQS can accept. */
    public const MAX_SQS_SIZE_KB = 256;

    /**
     * @var mixed[]
     */
    private array $message;

    private string $queueUrl;


    /**
     * @param array<mixed> $message
     */
    public function __construct(array $message, string $queueUrl)
    {
        $this->message = $message;
        $this->queueUrl = $queueUrl;
    }


    /**
     * @return mixed
     */
    public function getReceiptHandle()
    {
        return $this->message[SqsMessageFields::RECEIPT_HANDLE];
    }


    public function getBody(): string
    {
        return $this->message[SqsMessageFields::BODY];
    }


    /**
     * Returns system attributes
     *
     * @return mixed[]
     */
    public function getAttributes(): array
    {
        return $this->message[SqsMessageFields::ATTRIBUTES];
    }


    /**
     * Returns custom message attributes
     *
     * @return mixed[]
     */
    public function getMessageAttributes(): array
    {
        return $this->message[SqsMessageFields::MESSAGE_ATTRIBUTES];
    }


    public function getQueueUrl(): string
    {
        return $this->queueUrl;
    }


    public function getMessageId(): string
    {
        return $this->message[SqsMessageFields::MESSAGE_ID] ?? '';
    }


    /**
     * Returns true if message is bigger than 256 KB (AWS SQS message size limit), false otherwise
     *
     * @param array<string, array<string, string>> $messageAttributes
     */
    public static function isTooBig(string $messageBody, array $messageAttributes): bool
    {
        $messageSize = strlen($messageBody);
        foreach ($messageAttributes as $messageAttributeKey => $messageAttribute) {
            $messageSize += strlen($messageAttributeKey);
            $messageSize += strlen($messageAttribute['DataType']);
            $messageSize += strlen($messageAttribute['StringValue']);
        }

        return $messageSize > self::MAX_SQS_SIZE_KB * 1024;
    }
}
