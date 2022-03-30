<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Queue\AWSSQS\SqsClientException;
use BE\QueueManagement\Queue\AWSSQS\SqsClientFactory;
use PHPUnit\Framework\TestCase;

/**
 * @final
 */
class SqsClientFactoryTest extends TestCase
{
    public function testUnableToEstablishConnectionThrowsException(): void
    {
        $sqsClientFactory = new SqsClientFactory(
            [
                'region'  => 'eu-central-1',
                'version' => '2015-10-07',  // will throw Aws\Exception\UnresolvedApiException: 'The sqs service does not have version: 2015-10-07'
                'http' => [
                    'verify' => false,
                ],
            ],
        );

        $this->expectException(SqsClientException::class);
        $this->expectExceptionMessage('Unable to connect AWS SQS, try check connection parameters');

        $sqsClientFactory->create();
    }


    public function testMissingConfigKeysThrowsException(): void
    {
        $sqsClientFactory = new SqsClientFactory(
            [
                'http' => [
                    'verify' => false,
                ],
            ],
        );

        $this->expectException(SqsClientException::class);
        $this->expectExceptionMessage('Invalid connection config for AWS SQS, missing key/s (version, region)');

        $sqsClientFactory->create();
    }
}
