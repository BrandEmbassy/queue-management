<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS\MessageDeduplication;

use BE\QueueManagement\Queue\AWSSQS\MessageDeduplication\MessageDeduplicationDefault;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use BE\QueueManagement\Redis\RedisClient;
use malkusch\lock\mutex\NoMutex;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
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
    private const TEST_MESSAGE = [
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
            'QueueUrl' => [
                'StringValue' => self::TEST_QUEUE_URL,
                'DataType' => 'String',
            ],
        ],
    ];
    private const EXPECTED_REDIS_KEY = 'AWS_DEDUP_PREFIX_' . self::QUEUE_NAME . '_' . self::MESSAGE_ID;
    private const DEFAULT_TTL_VALUE = 300;


    public function testMessageNotYetSeen(): void
    {
        $message = new SqsMessage(self::TEST_MESSAGE, self::TEST_QUEUE_URL);
        $redisClient = Mockery::mock(RedisClient::class);
        $messageDeduplicationRedis = $this->creatMessageDeduplicationDefault($redisClient);

        $redisClient->expects('get')
            ->with(self::EXPECTED_REDIS_KEY)
            ->andReturn(null);

        $redisClient->expects('setWithTtl')
            ->with(self::EXPECTED_REDIS_KEY, '1', self::DEFAULT_TTL_VALUE);

        Assert::assertFalse($messageDeduplicationRedis->isDuplicate($message));
    }


    public function testMessageAlreadySeen(): void
    {
        $message = $this->createTestMessage();
        $redisClient = Mockery::mock(RedisClient::class);
        $messageDeduplicationRedis = $this->creatMessageDeduplicationDefault($redisClient);

        $redisClient->expects('get')
            ->with(self::EXPECTED_REDIS_KEY)
            ->andReturn('1');

        Assert::assertTrue($messageDeduplicationRedis->isDuplicate($message));
    }


    public function testMessageNotYetSeenThenAlreadySeen(): void
    {
        $message = $this->createTestMessage();
        $redisClient = Mockery::mock(RedisClient::class);
        $messageDeduplicationRedis = $this->creatMessageDeduplicationDefault($redisClient);

        $redisClient->expects('get')
            ->with(self::EXPECTED_REDIS_KEY)
            ->andReturn(null);

        $redisClient->expects('setWithTtl')
            ->with(self::EXPECTED_REDIS_KEY, '1', self::DEFAULT_TTL_VALUE);

        $redisClient->expects('get')
            ->with(self::EXPECTED_REDIS_KEY)
            ->andReturn('1');

        Assert::assertFalse($messageDeduplicationRedis->isDuplicate($message));
        Assert::assertTrue($messageDeduplicationRedis->isDuplicate($message));
    }


    private function createTestMessage(): SqsMessage
    {
        return new SqsMessage(self::TEST_MESSAGE, self::TEST_QUEUE_URL);
    }


    private function creatMessageDeduplicationDefault(RedisClient $redisClient): MessageDeduplicationDefault
    {
        return new MessageDeduplicationDefault($redisClient, new NoMutex(), new NullLogger());
    }
}
