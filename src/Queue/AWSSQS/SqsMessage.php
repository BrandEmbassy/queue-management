<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

/**
 * 
 * Represent SQS Message
 * 
 * AWS SQS API does not provide type for SQSMessahe, only type \Aws\Result. This class is simple abstraction over this generic type.
 * For details see https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.Result.html
 * 
 */
final class SqsMessage {

    /**
     * @var mixed[]
     */
    private $messageAttributes;

    /**
     * @param mixed[] $messageAttributes
     */    
    public function __construct(array $messageAttributes) 
    {
        $this->messageAttributes = $messageAttributes;
    }

    /**
     * @return mixed
     */    
    public function getReceiptHandle() 
    {
        return $this->messageAttributes['ReceiptHandle'];
    }

    public function getMessageBody(): string
    {
        return $this->messageAttributes['MessageBody'];
    }    

    /**
     * @return mixed[]
     */    
    public function getMessageAttributes() 
    {
        return $this->messageAttributes['MessageAttributes'];
    }

    public function getQueueUrl(): string
    {
        return $this->messageAttributes['QueueUrl'];
    }    

}