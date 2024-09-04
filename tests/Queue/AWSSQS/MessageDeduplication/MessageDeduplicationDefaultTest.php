<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS\MessageDeduplication;

use BE\QueueManagement\Queue\AWSSQS\MessageDeduplication\MessageDeduplicationDefault;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttribute;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttributeDataType;
use BE\QueueManagement\Redis\RedisClient;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Predis\Response\Status;
use Psr\Log\NullLogger;

/**
 * @final
 */
class MessageDeduplicationDefaultTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const TEST_QUEUE_URL = 'https://sqs.eu-central-1.amazonaws.com/583027123456/MyQueue1';

    private const QUEUE_NAME = 'MyQueue1';

    private const MESSAGE_ID = 'c176f71b-ea77-4b0e-af6a-d76246d77057';

    private const TEST_RECEIPT_HANDLE = 'AQEBMJRLDYbo...BYSvLGdGU9t8Q==';

    private const EXPECTED_REDIS_KEY = 'AWS_DEDUP_PREFIX_' . self::QUEUE_NAME . '_' . self::MESSAGE_ID;

    private const DEFAULT_TTL_VALUE = 7200;


    public function testMessageNotYetSeen(): void
    {
        $message = $this->createTestMessage();
        $clientMock = Mockery::mock(Client::class);
        $redisClient = new RedisClient($clientMock);

        $messageDeduplicationRedis = $this->creatMessageDeduplicationDefault($redisClient);

        $clientMock->expects('set')
            ->with(self::EXPECTED_REDIS_KEY, '1', 'EX', self::DEFAULT_TTL_VALUE, 'NX')
            ->andReturn(new Status('OK'));

        Assert::assertFalse($messageDeduplicationRedis->isDuplicate($message));
    }


    public function testMessageAlreadySeen(): void
    {
        $message = $this->createTestMessage();
        $clientMock = Mockery::mock(Client::class);
        $redisClient = new RedisClient($clientMock);
        $messageDeduplicationRedis = $this->creatMessageDeduplicationDefault($redisClient);

        $clientMock->expects('set')
            ->with(self::EXPECTED_REDIS_KEY, '1', 'EX', self::DEFAULT_TTL_VALUE, 'NX')
            ->andReturn(null);

        Assert::assertTrue($messageDeduplicationRedis->isDuplicate($message));
    }


    public function testMessageNotYetSeenThenAlreadySeen(): void
    {
        $message = $this->createTestMessage();
        $clientMock = Mockery::mock(Client::class);
        $redisClient = new RedisClient($clientMock);
        $messageDeduplicationRedis = $this->creatMessageDeduplicationDefault($redisClient);

        $clientMock->expects('set')
            ->with(self::EXPECTED_REDIS_KEY, '1', 'EX', self::DEFAULT_TTL_VALUE, 'NX')
            ->andReturn(new Status('OK'));

        $clientMock->expects('set')
            ->with(self::EXPECTED_REDIS_KEY, '1', 'EX', self::DEFAULT_TTL_VALUE, 'NX')
            ->andReturn(null);

        Assert::assertFalse($messageDeduplicationRedis->isDuplicate($message));
        Assert::assertTrue($messageDeduplicationRedis->isDuplicate($message));
    }


    private function createTestMessage(): SqsMessage
    {
        return new SqsMessage([
            'MessageId' => self::MESSAGE_ID,
            'ReceiptHandle' => self::TEST_RECEIPT_HANDLE,
            'MD5OfBody' => 'e0001b05d30f529eaf4bbbf585280a4c',
            'Body' => '{"jobUuid":"uuid-123","jobName":"exampleSqsJob","attempts":1,"createdAt":"2022-02-25T11:15:03+00:00","jobParameters":{"foo":"bar"}}',
            'Attributes' => [
                'SenderId' => 'AROAYPPZHWMXHMBX2SQUT:GroupAccessArchitectsSession',
                'ApproximateFirstReceiveTimestamp' => '1645787771287',
                'ApproximateReceiveCount' => '1',
                'SentTimestamp' => '1645787708045',
            ],
            'MD5OfMessageAttributes' => 'e4849a650dbb07b06723f9cf0ebe1f68',
            'MessageAttributes' => [
                'QueueUrl' => new SqsMessageAttribute(
                    'QueueUrl',
                    self::TEST_QUEUE_URL,
                    SqsMessageAttributeDataType::STRING,
                ),
            ],
        ], self::TEST_QUEUE_URL);
    }


    private function creatMessageDeduplicationDefault(RedisClient $redisClient): MessageDeduplicationDefault
    {
        return new MessageDeduplicationDefault($redisClient, new NullLogger());
    }
}
