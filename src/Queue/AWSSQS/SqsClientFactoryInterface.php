<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\Sqs\SqsClient;

/**
 * 
 * Defines interface for SQS client factory.
 * 
 * Implementing class is assigned all properties needed to create SQS client and contains technnical logic of validating and creating SqsClient
 * 
 * https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.Sqs.SqsClient.html
 */
interface ConnectionFactoryInterface
{
    public function create(): SqsClient;
}
