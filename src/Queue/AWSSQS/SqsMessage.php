<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

/**
 * Represent SQS Message
 *
 * AWS SQS API does not provide type for SQSMessahe, only type \Aws\Result. This class is simple abstraction over this generic type.
 * For details see https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.Result.html
 *
 * @final
 */
class SqsMessage
{
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
        return $this->message[SqsMessageFields::RECEIPTHANDLE];
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
        return $this->message[SqsMessageFields::MESSAGEATTRIBUTES];
    }


    public function getQueueUrl(): string
    {
        return $this->queueUrl;
    }


    public function getMessageId(): string
    {
        return $this->message[SqsMessageFields::MESSAGEID];
    }
}
