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
    private $message;

    /**
     * @var string
     */
    private $queueUrl;

    /**
     * @param mixed[] $messageAttributes
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
        return $this->message['ReceiptHandle'];
    }

    public function getBody(): string
    {
        return $this->message['Body'];
    }    

    /**
     * Returns system attributes
     * @return mixed[]
     */    
    public function getAttributes() 
    {
        return $this->message['Attributes'];
    }
    
    /**
     * Returns custom message attributes
     * @return mixed[]
     */    
    public function getMessageAttributes() 
    {
        return $this->message['MessageAttributes'];
    }    

    /**
     * @return string
     */    
    public function getQueueUrl(): string
    {
        return $this->queueUrl;
    }    
}