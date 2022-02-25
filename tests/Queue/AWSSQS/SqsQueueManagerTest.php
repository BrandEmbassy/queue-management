<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Queue\AWSSQS\SqsClientException;
use BE\QueueManagement\Queue\AWSSQS\SqsClientFactory;
use BE\QueueManagement\Queue\AWSSQS\SqsQueueManager;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\BE\QueueManagement\Jobs\ExampleJob;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;
use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;
use Aws\CommandInterface;
use Aws\Result;

final class SqsQueueManagerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public const DUMMY_QUEUE_URL = 'https://sqs.eu-central-1.amazonaws.com/583027123456/MyQueue1';
    public const DUMMY_RECEIPT_HANDLE = 'AQEBMJRLDYbo...BYSvLGdGU9t8Q==';


    /**
     * @var SqsClientFactory&MockInterface
     */
    private $sqsClientFactoryMock;

    /**
     * @var LoggerInterface&MockInterface
     */
    private $loggerMock;

    /**
     * @var SqsClient&MockInterface
     */    
    private $sqsClientMock;

    /**
     * @var CommandInterface;&MockInterface
     */    
    private $awsCommandMock;

    /**
     * @var Result;&MockInterface
     */    
    private $awsResultMock;


    public function setUp(): void
    {
        parent::setUp();
        $this->sqsClientFactoryMock = Mockery::mock(SqsClientFactory::class);
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->sqsClientMock = Mockery::mock(SqsClient::class);
        $this->awsCommandMock = Mockery::mock(CommandInterface::class);
        $this->awsResultMock = Mockery::mock(Result::class);
    }

    public function testPush(): void
    {
        $this->expectSetUpConnection();

        $this->loggerMock->shouldReceive('info')
            ->with('Job (exampleJob) [some-job-uud] pushed into exampleJobQueue queue')
            ->once();

        $exampleJob = $this->createExampleJob();

        $this->sqsClientMock->shouldReceive('sendMessage')
            ->with(
                Mockery::on(
                    static function (array $message) use ($exampleJob): bool {
                        return SqsQueueManagerTest::messageCheckOk($message, $exampleJob, 0);
                    }
                )
            )
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->push($exampleJob);
    }  
    
    public function testPushDelayed(): void
    {
        $this->expectSetUpConnection();

        $exampleJob = $this->createExampleJob();

        $this->sqsClientMock->shouldReceive('sendMessage')
            ->with(
                Mockery::on(
                    static function (array $message) use ($exampleJob): bool {
                        return SqsQueueManagerTest::messageCheckOk($message, $exampleJob, 5);
                    }
                )
            )
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->pushDelayed($exampleJob, 5);
    }

    public function testPushDelayedWithMilliSeconds(): void
    {
        $this->expectSetUpConnection();

        $exampleJob = $this->createExampleJob();

        $this->sqsClientMock->shouldReceive('sendMessage')
            ->with(
                Mockery::on(
                    static function (array $message) use ($exampleJob): bool {
                        return SqsQueueManagerTest::messageCheckOk($message, $exampleJob, 5);
                    }
                )
            )
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->pushDelayedWithMilliseconds($exampleJob, 5000);
    }

    public function testPushWithReconnect(): void
    {
        $this->expectSetUpConnection(2);

        $this->loggerMock->shouldReceive('info')
            ->with('Job (exampleJob) [some-job-uud] pushed into exampleJobQueue queue')
            ->once();

        $exampleJob = $this->createExampleJob();
        
        $awsException = new AwsException('Some nasty error',  $this->awsCommandMock);
        
        $this->sqsClientMock->shouldReceive('sendMessage')
            ->with(
                Mockery::on(
                    static function (array $message) use ($exampleJob): bool {
                        return SqsQueueManagerTest::messageCheckOk($message, $exampleJob, 0);
                    }
                )
            )
            ->once()
            ->andThrow($awsException);

        $this->sqsClientMock->shouldReceive('sendMessage')
            ->with(
                Mockery::on(
                    static function (array $message) use ($exampleJob): bool {
                        return SqsQueueManagerTest::messageCheckOk($message, $exampleJob, 0);
                    }
                )
            )
            ->once();

        $this->loggerMock->shouldReceive('warning')
            ->with(
                'Reconnecting: Some nasty error',
                Mockery::hasKey('queueName')
            )
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->push($exampleJob);
    }    


    public function testConsume(): void
    {
        $this->expectSetUpConnection();

        $expectedCallback = function (SqsMessage $message): void {};

        $messages = $this->getSampleSqsMessages();

        $this->awsResultMock->shouldReceive('get')
            ->with('Messages')
            ->andReturn($messages)
            ->once();

        $this->sqsClientMock->shouldReceive('receiveMessage')
            ->with([
                'AttributeNames' => ['All'],
                'MaxNumberOfMessages' => 10,
                'MessageAttributeNames' => ['All'],
                'QueueUrl' => self::DUMMY_QUEUE_URL,
                'WaitTimeSeconds' => 10,
            ])
            ->andReturn($this->awsResultMock)
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->consumeMessages(
            $expectedCallback,
            self::DUMMY_QUEUE_URL,
            [
                SqsQueueManager::MAX_NUMBER_OF_MESSAGES => 10,
                SqsQueueManager::UNIT_TEST_CONTEXT => true // this will end consumer loop after first iteration
            ]
        );
    }

    public function testConsumeWithReconnect(): void
    {
        $this->expectSetUpConnection(2);

        $expectedCallback = function (SqsMessage $message): void {};

        $awsException = new AwsException('Some nasty error',  $this->awsCommandMock);


        $this->sqsClientMock->shouldReceive('receiveMessage')
            ->with([
                'AttributeNames' => ['All'],
                'MaxNumberOfMessages' => 10,
                'MessageAttributeNames' => ['All'],
                'QueueUrl' => self::DUMMY_QUEUE_URL,
                'WaitTimeSeconds' => 10,
            ])
            ->once()
            ->andThrow($awsException);

         $messages = $this->getSampleSqsMessages();

         $this->awsResultMock->shouldReceive('get')
            ->with('Messages')
            ->andReturn($messages)
            ->once();

        $this->sqsClientMock->shouldReceive('receiveMessage')
            ->with([
                'AttributeNames' => ['All'],
                'MaxNumberOfMessages' => 10,
                'MessageAttributeNames' => ['All'],
                'QueueUrl' => self::DUMMY_QUEUE_URL,
                'WaitTimeSeconds' => 10,
            ])
            ->once()
            ->andReturn($this->awsResultMock);

        $this->loggerMock->shouldReceive('warning')
            ->with('AwsException: Some nasty error', ['exception' => $awsException])
            ->once();

        $this->loggerMock->shouldReceive('warning')
            ->with('Reconnecting: Some nasty error', Mockery::hasKey('queueName'))
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->consumeMessages(
            $expectedCallback,
            self::DUMMY_QUEUE_URL,
            [
                SqsQueueManager::MAX_NUMBER_OF_MESSAGES => 10,
                SqsQueueManager::UNIT_TEST_CONTEXT => true // this will end consumer loop after first iteration
            ]
        );
    }    

    /**
     * @return array<mixed>
     */
    private function getSampleSqsMessages(): array {
        $messages = array([
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
        ]);
        return $messages;
    }

    private static function messageCheckOk(array $message, ExampleJob $exampleJob, int $delay): bool
    {
        return $message['MessageBody'] === $exampleJob->toJson()
            && $message[SqsMessage::ATTR_DELAYSECONDS] === $delay
            && $message[SqsMessage::ATTR_QUEUEURL] === ExampleJobDefinition::QUEUE_NAME
            && $message[SqsMessage::ATTR_MESSAGEATTRIBUTES][SqsMessage::ATTR_QUEUEURL]['StringValue'] === ExampleJobDefinition::QUEUE_NAME;        
    }
    

    private function createExampleJob(): ExampleJob
    {
        return new ExampleJob();
    }


    private function createQueueManager(): SqsQueueManager
    {
        return new SqsQueueManager($this->sqsClientFactoryMock, $this->loggerMock);
    }

    private function expectSetUpConnection(int $connectionIsCreatedTimes = 1): void
    {
        $this->sqsClientFactoryMock->shouldReceive('create')
            ->withNoArgs()
            ->times($connectionIsCreatedTimes)
            ->andReturn($this->sqsClientMock);
    }    
}