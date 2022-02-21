<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\Result;

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
     * @var \Aws\Result
     */
    private $awsResult;

    public function __construct(Result $awsResult) {
        $this->awsResult = $awsResult;
    }
}