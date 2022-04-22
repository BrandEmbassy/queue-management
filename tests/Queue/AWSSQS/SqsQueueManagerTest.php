<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\S3\S3Client;
use Aws\Sqs\SqsClient;
use BE\QueueManagement\Queue\AWSSQS\S3ClientFactory;
use BE\QueueManagement\Queue\AWSSQS\SqsClientFactory;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageFields;
use BE\QueueManagement\Queue\AWSSQS\SqsQueueManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\BE\QueueManagement\Jobs\ExampleJob;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;
use function count;

/**
 * @final
 */
class SqsQueueManagerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public const DUMMY_QUEUE_URL = 'https://sqs.eu-central-1.amazonaws.com/583027123456/MyQueue1';
    public const DUMMY_RECEIPT_HANDLE = 'AQEBMJRLDYbo...BYSvLGdGU9t8Q==';

    /**
     * @var SqsClientFactory&MockInterface
     */
    private $sqsClientFactoryMock;

    /**
     * @var S3ClientFactory&MockInterface
     */
    private $s3ClientFactoryMock;

    /**
     * @var LoggerInterface&MockInterface
     */
    private $loggerMock;

    /**
     * @var SqsClient&MockInterface
     */
    private $sqsClientMock;

    /**
     * @var S3Client&MockInterface
     */
    private $s3ClientMock;

    /**
     * @var CommandInterface<mixed>&MockInterface
     */
    private $awsCommandMock;

    /**
     * @var Result<mixed>&MockInterface
     */
    private $awsResultMock;


    public function setUp(): void
    {
        parent::setUp();
        $this->sqsClientFactoryMock = Mockery::mock(SqsClientFactory::class);
        $this->s3ClientFactoryMock = Mockery::mock(S3ClientFactory::class);
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->sqsClientMock = Mockery::mock(SqsClient::class);
        $this->s3ClientMock = Mockery::mock(S3Client::class);
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
                    static fn(array $message): bool => self::messageCheckOk($message, $exampleJob, 0),
                ),
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
                    static fn(array $message): bool => self::messageCheckOk($message, $exampleJob, 5),
                ),
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
                    static fn(array $message): bool => self::messageCheckOk($message, $exampleJob, 5),
                ),
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

        $awsException = new AwsException('Some nasty error', $this->awsCommandMock);

        $this->sqsClientMock->shouldReceive('sendMessage')
            ->with(
                Mockery::on(
                    static fn(array $message): bool => self::messageCheckOk($message, $exampleJob, 0),
                ),
            )
            ->once()
            ->andThrow($awsException);

        $this->sqsClientMock->shouldReceive('sendMessage')
            ->with(
                Mockery::on(
                    static fn(array $message): bool => self::messageCheckOk($message, $exampleJob, 0),
                ),
            )
            ->once();

        $this->loggerMock->shouldReceive('warning')
            ->with(
                'Reconnecting: Some nasty error',
                Mockery::hasKey('queueName'),
            )
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->push($exampleJob);
    }


    public function testConsume(): void
    {
        $this->expectSetUpConnection();

        $expectedCallback = function (SqsMessage $message): void {
        };

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
            ],
        );
    }


    public function testConsumeWithReconnect(): void
    {
        $this->expectSetUpConnection(2);

        $expectedCallback = function (SqsMessage $message): void {
        };

        $awsException = new AwsException('Some nasty error', $this->awsCommandMock);

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
            ],
        );
    }


    /**
     * @return array<mixed>
     */
    private function getSampleSqsMessages(): array
    {
        $messages = [
            [
                'MessageId' => 'c176f71b-ea77-4b0e-af6a-d76246d77057',
                'ReceiptHandle' => self::DUMMY_RECEIPT_HANDLE,
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
                        'StringValue' => self::DUMMY_QUEUE_URL,
                        'DataType' => 'String',
                    ],
                ],
            ],
        ];

        return $messages;
    }


    /**
     * @param array<mixed> $message
     */
    private static function messageCheckOk(array $message, ExampleJob $exampleJob, int $delay): bool
    {
        return $message['MessageBody'] === $exampleJob->toJson()
            && $message[SqsMessageFields::DELAYSECONDS] === $delay
            && $message[SqsMessageFields::QUEUEURL] === ExampleJobDefinition::QUEUE_NAME
            && $message[SqsMessageFields::MESSAGEATTRIBUTES][SqsMessageFields::QUEUEURL]['StringValue'] === ExampleJobDefinition::QUEUE_NAME;
    }


    private function createExampleJob(): ExampleJob
    {
        return new ExampleJob();
    }


    private function createQueueManager(): SqsQueueManager
    {
        return new SqsQueueManager($this->sqsClientFactoryMock, $this->s3ClientFactoryMock, $this->loggerMock, 1);
    }


    private function expectSetUpConnection(int $connectionIsCreatedTimes = 1): void
    {
        $this->sqsClientFactoryMock->shouldReceive('create')
            ->withNoArgs()
            ->times($connectionIsCreatedTimes)
            ->andReturn($this->sqsClientMock);

        $this->s3ClientFactoryMock->shouldReceive('create')
            ->withNoArgs()
            ->times($connectionIsCreatedTimes)
            ->andReturn($this->s3ClientMock);
    }


    public function testFromAwsResultMessagesDownloadingFromS3(): void
    {
        $queueUrl = 'https://sqs.eu-central-1.amazonaws.com/1234567891/SomeQueue';
        $messages = [
            0 => [
                'MessageId' => '96819875-6e43-4a14-9652-6b5d239f5e1b',
                'ReceiptHandle' => 'AQEB...',
                'MD5OfBody' => 'db9b6a326e8c7336d4303d9a4b8f3e11',
                'Body' => '[[{"statusCode":200,"effectiveUri":"https:\\/\\/dfo-webhooksender-s3.s3.eu-central-1.amazonaws.com\\/de2710e6-56b8-47cc-95fe-5aae916ef2c8.json","headers":{"x-amz-id-2":"2MSq\\/GpTM6k6yPHZJtsmsYBYKLJLmd+OyF2CTsTlLQfZlw02\\/BFCqhdWJnQ+71TbozrsxYk\\/TfQ=","x-amz-request-id":"SMJJ5QJFZ0EACVKD","date":"Thu, 21 Apr 2022 11:07:04 GMT","etag":"\\"eff36c85eeeebeaf8a583bf55776120b\\"","server":"AmazonS3","content-length":"0"},"transferStats":{"http":[[]]}},"https:\\/\\/dfo-webhooksender-s3.s3.eu-central-1.amazonaws.com\\/de2710e6-56b8-47cc-95fe-5aae916ef2c8.json"],{"s3BucketName":"dfo-webhooksender-s3","s3Key":"de2710e6-56b8-47cc-95fe-5aae916ef2c8.json"}]',
                'Attributes' =>
                    [
                        'SenderId' => 'AROAYPPZHWMXHMBX2SQUT:SomeRoleSession',
                        'ApproximateFirstReceiveTimestamp' => '1650539417093',
                        'ApproximateReceiveCount' => '1',
                        'SentTimestamp' => '1650539224000',
                    ],
                'MD5OfMessageAttributes' => 'e4849a650dbb07b06723f9cf0ebe1f68',
                'MessageAttributes' =>
                    [
                        'QueueUrl' =>
                            [
                                'StringValue' => $queueUrl,
                                'DataType' => 'String',
                            ],
                    ],
            ],
        ];

        $messageBody = '{"jobUuid":"uuid-123","jobName":"exampleSqsJob","attempts":1,"createdAt":"2022-04-21T14:05:47+00:00","jobParameters":{"foo":"bar"}}';

        $this->expectSetUpConnection();

        $queueManager = $this->createQueueManager();

        $this->s3ClientMock->shouldReceive('getObject')
            ->once()
            ->andReturn($this->awsResultMock);

        $this->awsResultMock->shouldReceive('get')
            ->once()
            ->with('Body')
            ->andReturn($messageBody);

        $this->loggerMock->shouldReceive('warning')
            ->with('Message with ID 96819875-6e43-4a14-9652-6b5d239f5e1b will be downloaded from S3 bucket: dfo-webhooksender-s3. Key: de2710e6-56b8-47cc-95fe-5aae916ef2c8.json')
            ->once();

        $sqsMessages = $queueManager->fromAwsResultMessages($messages, $queueUrl);

        Assert::assertTrue(count($sqsMessages) === 1);
        Assert::assertTrue($sqsMessages[0]->getBody() === $messageBody);
    }


    public function testFromAwsResultMessagesNotDownloadingFromS3(): void
    {
        $queueUrl = 'https://sqs.eu-central-1.amazonaws.com/1234567891/SomeQueue';
        $messages = [
            0 => [
                'MessageId' => '46e68a1c-5a26-43a6-8a14-533c5f568220',
                'ReceiptHandle' => 'AQEB...',
                'MD5OfBody' => '0a7adfb0fdeaafa6dccfd81aa1cd53b1',
                'Body' => '{"jobUuid":"uuid-123","jobName":"exampleSqsJob","attempts":1,"createdAt":"2022-04-22T09:11:05+00:00","jobParameters":{"foo":"bar"}}',
                'Attributes' =>
                    [
                        'SenderId' => 'AROAYPPZHWMXHMBX2SQUT:SomeRoleSession',
                        'ApproximateFirstReceiveTimestamp' => '1650618745238',
                        'ApproximateReceiveCount' => '1',
                        'SentTimestamp' => '1650618639695',
                    ],
                'MD5OfMessageAttributes' => 'e4849a650dbb07b06723f9cf0ebe1f68',
                'MessageAttributes' =>
                    [
                        'QueueUrl' =>
                            [
                                'StringValue' => 'https://sqs.eu-central-1.amazonaws.com/1234567891/SomeQueue',
                                'DataType' => 'String',
                            ],
                    ],
            ],
        ];

        $messageBodyExpected = '{"jobUuid":"uuid-123","jobName":"exampleSqsJob","attempts":1,"createdAt":"2022-04-22T09:11:05+00:00","jobParameters":{"foo":"bar"}}';

        $this->expectSetUpConnection();

        $queueManager = $this->createQueueManager();

        $this->s3ClientMock->shouldNotReceive('getObject');

        $this->awsResultMock->shouldNotReceive('get');

        $this->loggerMock->shouldNotReceive('warning')
            ->with('Message with ID 96819875-6e43-4a14-9652-6b5d239f5e1b will be downloaded from S3 bucket: dfo-webhooksender-s3. Key: de2710e6-56b8-47cc-95fe-5aae916ef2c8.json');

        $sqsMessages = $queueManager->fromAwsResultMessages($messages, $queueUrl);
        $messageBodyReal = $sqsMessages[0]->getBody();

        Assert::assertTrue(count($sqsMessages) === 1);
        Assert::assertTrue($messageBodyReal === $messageBodyExpected);
    }
}
