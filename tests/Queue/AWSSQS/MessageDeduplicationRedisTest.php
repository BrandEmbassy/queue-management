<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use BE\QueueManagement\Queue\AWSSQS\MessageDeduplicationRedis;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use BE\QueueManagement\Redis\RedisClient;
use Predis\Client;
use Predis\Response\Status;
use malkusch\lock\mutex\PredisMutex;

final class MessageDeduplicationRedisTest extends TestCase 
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

    /**
     * @var Client&MockInterface
     */    
    private $predisClient;

    /**
     * @var Status&MockInterface
     */    
    private $statusMock;
    

    public const DUMMY_QUEUE_URL = 'https://sqs.eu-central-1.amazonaws.com/583027123456/MyQueue1';
    public const DUMMY_RECEIPT_HANDLE1 = 'AQEBMJRLDYbo...BYSvLGdGU9t8Q==';
    public const DUMMY_RECEIPT_HANDLE2 = 'AQEBMJRLDYbo...BYSvLGdGU9t9Q==';

    public const DUMMY_MESSAGES = array(
        [
            'MessageId' => 'c176f71b-ea77-4b0e-af6a-d76246d77057',
            'ReceiptHandle' => self::DUMMY_RECEIPT_HANDLE1,
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
        ],
        [
            'MessageId' => 'c176f71b-ea77-4b0e-af6a-d76246d77058',
            'ReceiptHandle' => self::DUMMY_RECEIPT_HANDLE2,
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
        $this->predisClient = Mockery::mock(Client::class);
        $this->statusMock = Mockery::mock(Status::class);
    }


    public function testMessageNotYetSeen(): void
    {

        $message = new SqsMessage(self::DUMMY_MESSAGES[0], self::DUMMY_QUEUE_URL);
        $messageDeduplicationRedis = $this->createMessageDeduplicationRedis();

        $this->redisClientMock->shouldReceive('getRedisClient')
            ->once()
            ->andReturn($this->predisClient);

        $this->redisClientMock->shouldReceive('get')
            ->twice()
            ->andReturn(null);

        $this->predisClient->shouldReceive('set')
            ->once()
            ->andReturn($this->statusMock);

        $this->assertFalse($messageDeduplicationRedis->isDuplicate($message));
    }

    private function createMessageDeduplicationRedis(): MessageDeduplicationRedis
    {
        return new MessageDeduplicationRedis($this->loggerMock, $this->redisClientMock, 3, 'queue1');
    }
}