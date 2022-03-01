<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use BE\QueueManagement\Queue\AWSSQS\MessageDeduplicationDefault;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use BE\QueueManagement\Redis\RedisClient;
use malkusch\lock\mutex\NoMutex;

final class MessageDeduplicationDefaultTest extends TestCase 
{
    use MockeryPHPUnitIntegration;

    /**
     * @var LoggerInterface&MockInterface
     */
    private $loggerMock;

    /**
     * @var RedisClient&MockInterface
     */
    private $redisClientMock;


    public const DUMMY_QUEUE_URL = 'https://sqs.eu-central-1.amazonaws.com/583027123456/MyQueue1';
    public const DUMMY_RECEIPT_HANDLE = 'AQEBMJRLDYbo...BYSvLGdGU9t8Q==';

    public const DUMMY_MESSAGES = array(
        [
            'MessageId' => 'c176f71b-ea77-4b0e-af6a-d76246d77057',
            'ReceiptHandle' => self::DUMMY_RECEIPT_HANDLE,
            'MD5OfBody' => 'e0001b05d30f529eaf4bbbf585280a4c',
            'Body' => '{"jobUuid":"uuid-123","jobName":"exampleSqsJob","attempts":1,"createdAt":"2022-02-25T11:15:03+00:00","jobParameters":{"foo":"bar"}}',
            'Attributes' => [
                'SenderId' => 'AROAYPPZHWMXHMBX2SQUT:GroupAccessArchitectsSession',
                'ApproximateFirstReceiveTimestamp'=>'1645787771287',
                'ApproximateReceiveCount' => '1',
                'SentTimestamp'=>'1645787708045',
            ],
            'MD5OfMessageAttributes'=>'e4849a650dbb07b06723f9cf0ebe1f68',
            'MessageAttributes'=> [
                'QueueUrl' => [
                    'StringValue' => self::DUMMY_QUEUE_URL,
                    'DataType' => 'String'
                ]
            ]                    
        ]
    );    

    public function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->redisClientMock = Mockery::mock(RedisClient::class);
    }


    public function testMessageNotYetSeen(): void
    {

        $message = new SqsMessage(self::DUMMY_MESSAGES[0], self::DUMMY_QUEUE_URL);
        $messageDeduplicationRedis = $this->creatMessageDeduplicationDefault();

        $this->redisClientMock->shouldReceive('get')
            ->once()
            ->andReturn(null);

        $this->redisClientMock->shouldReceive('setWithTTL')
            ->once();

        $this->assertFalse($messageDeduplicationRedis->isDuplicate($message));
    }

    public function testMessageAlreadySeen(): void
    {

        $message = $this->creaDummyMessage();
        $messageDeduplicationRedis = $this->creatMessageDeduplicationDefault();

        $this->redisClientMock->shouldReceive('get')
            ->once()
            ->andReturn("1");

        $this->assertTrue($messageDeduplicationRedis->isDuplicate($message));
    }    

    public function testMessageNotYetSeenThenAlreadySeen(): void
    {

        $message = $this->creaDummyMessage();
        $messageDeduplicationRedis = $this->creatMessageDeduplicationDefault();

        $this->redisClientMock->shouldReceive('get')
            ->once()
            ->andReturn(null);

        $this->redisClientMock->shouldReceive('setWithTTL')
            ->once();

            $this->redisClientMock->shouldReceive('get')
            ->once()
            ->andReturn("1");

        $this->assertFalse($messageDeduplicationRedis->isDuplicate($message));
        $this->assertTrue($messageDeduplicationRedis->isDuplicate($message));
    }    


    private function creaDummyMessage(): SqsMessage
    {
     return new SqsMessage(self::DUMMY_MESSAGES[0], self::DUMMY_QUEUE_URL);   
    }

    private function creatMessageDeduplicationDefault(): MessageDeduplicationDefault
    {
        return new MessageDeduplicationDefault($this->loggerMock, $this->redisClientMock, new NoMutex(), 'queue1');
    }
}