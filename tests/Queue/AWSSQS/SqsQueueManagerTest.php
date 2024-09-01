<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\S3\S3Client;
use Aws\Sqs\SqsClient;
use BE\QueueManagement\Observability\AfterExecutionPlannedEvent;
use BE\QueueManagement\Observability\AfterMessageSentEvent;
use BE\QueueManagement\Observability\BeforeExecutionPlannedEvent;
use BE\QueueManagement\Observability\BeforeMessageSentEvent;
use BE\QueueManagement\Observability\PlannedExecutionStrategyEnum;
use BE\QueueManagement\Queue\AWSSQS\DelayedJobSchedulerInterface;
use BE\QueueManagement\Queue\AWSSQS\S3ClientFactory;
use BE\QueueManagement\Queue\AWSSQS\SqsClientFactory;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use BE\QueueManagement\Queue\AWSSQS\SqsQueueManager;
use BE\QueueManagement\Queue\AWSSQS\SqsSendingMessageFields;
use BrandEmbassy\DateTime\FrozenDateTimeImmutableFactory;
use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Nette\Utils\Json;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tests\BE\QueueManagement\Jobs\ExampleJob;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;
use function sprintf;

/**
 * @final
 */
class SqsQueueManagerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const QUEUE_URL = 'https://sqs.eu-central-1.amazonaws.com/583027123456/MyQueue1';

    private const CUSTOM_MESSAGE_ATTRIBUTE = 'customMessageAttribute';

    private const CUSTOM_MESSAGE_ATTRIBUTE_VALUE = 'customMessageAttributeValue';

    public const CUSTOM_MESSAGE_ATTRIBUTES = [
        self::CUSTOM_MESSAGE_ATTRIBUTE => [
            'DataType' => 'String',
            'StringValue' => self::CUSTOM_MESSAGE_ATTRIBUTE_VALUE,
        ],
    ];

    private const RECEIPT_HANDLE = 'AQEBMJRLDYbo...BYSvLGdGU9t8Q==';

    private const S3_BUCKET_NAME = 'thisIsS3Bucket';

    private const FROZEN_DATE_TIME = '2016-08-15T15:00:00+00:00';

    private const SQS_MESSAGE_ID = '96819875-6e43-4a14-9652-6b5d239f5e1b';

    private TestLogger $loggerMock;

    /**
     * @var SqsClient&MockInterface
     */
    private SqsClient $sqsClientMock;

    /**
     * @var S3Client&MockInterface
     */
    private S3Client $s3ClientMock;

    /**
     * @var CommandInterface<mixed>&MockInterface
     */
    private CommandInterface $awsCommandMock;

    /**
     * @var Result<mixed>&MockInterface
     */
    private Result $awsResultMock;


    protected function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = new TestLogger();
        $this->sqsClientMock = Mockery::mock(SqsClient::class);
        $this->s3ClientMock = Mockery::mock(S3Client::class);
        $this->awsCommandMock = Mockery::mock(CommandInterface::class);
        $this->awsResultMock = Mockery::mock(Result::class);
    }


    #[DataProvider('queueNameDataProvider')]
    public function testPush(string $queueName, string $queueNamePrefix): void
    {
        $queueManager = $this->createQueueManagerWithExpectations($queueNamePrefix);

        $this->loggerMock->hasInfo('Job (exampleJob) [some-job-uuid] pushed into exampleJobQueue queue');

        $exampleJob = $this->createExampleJob($queueName);

        $this->sqsClientMock->expects('sendMessage')
            ->with(
                Mockery::on(
                    fn(array $message): bool => $this->messageCheckOk($message, $exampleJob->toJson(), 0),
                ),
            )
            ->andReturn($this->createSqsSendMessageResultMock());

        $queueManager->push($exampleJob);
    }


    #[DataProvider('queueNameDataProvider')]
    public function testPushWithInvalidCharacters(string $queueName, string $queueNamePrefix): void
    {
        $queueManager = $this->createQueueManagerWithExpectations($queueNamePrefix);

        $this->loggerMock->hasInfo('Job (exampleJob) [some-job-uuid] pushed into exampleJobQueue queue');

        $exampleJobWithInvalidCharacter = new ExampleJob(
            ExampleJobDefinition::create()
                ->withQueueName($queueName),
            'This ￾is random text.',
        );
        $exampleJobWithInvalidCharacter->setMessageAttribute(
            self::CUSTOM_MESSAGE_ATTRIBUTE,
            self::CUSTOM_MESSAGE_ATTRIBUTE_VALUE,
        );

        $exampleJobWithValidCharacter = new ExampleJob(
            ExampleJobDefinition::create()
                ->withQueueName($queueName),
            'This is random text.',
        );
        $exampleJobWithValidCharacter->setMessageAttribute(
            self::CUSTOM_MESSAGE_ATTRIBUTE,
            self::CUSTOM_MESSAGE_ATTRIBUTE_VALUE,
        );

        $this->sqsClientMock->expects('sendMessage')
            ->with(
                Mockery::on(
                    fn(array $message): bool => $this->messageCheckOk(
                        $message,
                        $exampleJobWithValidCharacter->toJson(),
                        0,
                    ),
                ),
            )
            ->andReturn($this->createSqsSendMessageResultMock());

        $queueManager->push($exampleJobWithInvalidCharacter);
    }


    #[DataProvider('queueNameDataProvider')]
    public function testPushWithTooBigMessage(string $queueName, string $queueNamePrefix): void
    {
        $queueManager = $this->createQueueManagerWithExpectations($queueNamePrefix);

        $this->loggerMock->hasInfo('Job (exampleJob) [some-job-uuid] pushed into exampleJobQueue queue');

        $exampleJob = ExampleJob::createTooBigForSqs(
            ExampleJobDefinition::create()
                ->withQueueName($queueName),
        );

        $this->s3ClientMock->expects('upload')
            ->with(
                self::S3_BUCKET_NAME,
                TestOnlyMessageKeyGenerator::S3_KEY,
                $exampleJob->toJson(),
            )
            ->andReturn(
                new Result([
                    '@metadata' => 'thisIsMetadata',
                    'ObjectURL' => 'thisIsObjectUrl',
                ]),
            );

        $messageBody = sprintf(
            '[["thisIsMetadata","thisIsObjectUrl"],{"s3BucketName":"%s","s3Key":"\/sqsQueueJobs\/jobUuid.json"}]',
            self::S3_BUCKET_NAME,
        );

        $this->sqsClientMock->expects('sendMessage')
            ->with(
                Mockery::on(
                    fn(array $message): bool => $this->messageCheckOk($message, $messageBody, 0, false),
                ),
            )
            ->andReturn($this->createSqsSendMessageResultMock());

        $queueManager->push($exampleJob);
    }


    #[DataProvider('queueNameDataProvider')]
    public function testPushDelayed(string $queueName, string $queueNamePrefix): void
    {
        $queueManager = $this->createQueueManagerWithExpectations($queueNamePrefix);

        $exampleJob = $this->createExampleJob($queueName);

        $this->sqsClientMock->expects('sendMessage')
            ->with(
                Mockery::on(
                    fn(array $message): bool => $this->messageCheckOk($message, $exampleJob->toJson(), 5),
                ),
            )
            ->andReturn($this->createSqsSendMessageResultMock());

        $this->loggerMock->hasInfo('Job (exampleJob) [some-job-uuid] pushed into exampleJobQueue queue');

        $queueManager->pushDelayed($exampleJob, 5);
    }


    #[DataProvider('queueNameDataProvider')]
    public function testPushDelayedWithJobDelayOverSqsMaxDelayLimit(string $queueName, string $queueNamePrefix): void
    {
        $queueManager = $this->createQueueManagerWithExpectations($queueNamePrefix);

        $exampleJob = $this->createExampleJob($queueName);

        $expectedMessageBody = [
            'jobUuid' => 'some-job-uuid',
            'jobName' => 'exampleJob',
            'attempts' => 1,
            'createdAt' => '2018-08-01T10:15:47+01:00',
            'jobParameters' => [
                'foo' => 'bar',
            ],
            'executionPlannedAt' => '2016-08-15T15:30:00+00:00',
        ];

        $this->sqsClientMock->expects('sendMessage')
            ->with(
                Mockery::on(
                    fn(array $message): bool => $this->messageCheckOk(
                        $message,
                        Json::encode($expectedMessageBody),
                        900,
                    ),
                ),
            )
            ->andReturn($this->createSqsSendMessageResultMock());

        $this->loggerMock->hasInfo(
            'Requested delay is greater than SQS limit. Job execution has been planned and will be requeued until then.',
        );
        $this->loggerMock->hasInfo('Job (exampleJob) [some-job-uuid] pushed into exampleJobQueue queue');

        $queueManager->pushDelayed($exampleJob, 1800);
    }


    #[DataProvider('queueNameDataProvider')]
    public function testPushDelayedWithJobDelayOverSqsMaxDelayLimitAndEventDispatcher(string $queueName, string $queueNamePrefix): void
    {
        $exampleJob = $this->createExampleJob($queueName);

        /** @var BeforeExecutionPlannedEvent&MockInterface $beforeExecutionPlannedEventMock */
        $beforeExecutionPlannedEventMock = Mockery::mock(BeforeExecutionPlannedEvent::class);
        /** @var BeforeExecutionPlannedEvent&MockInterface $afterExecutionPlannedEventMock */
        $afterExecutionPlannedEventMock = Mockery::mock(AfterExecutionPlannedEvent::class);
        /** @var BeforeMessageSentEvent&MockInterface $beforeMessageSentEventMock */
        $beforeMessageSentEventMock = Mockery::mock(BeforeMessageSentEvent::class);
        /** @var AfterMessageSentEvent&MockInterface $afterMessageSentEventMock */
        $afterMessageSentEventMock = Mockery::mock(AfterMessageSentEvent::class);

        /** @var EventDispatcherInterface&MockInterface $eventDispatcherMock */
        $eventDispatcherMock = Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcherMock
            ->expects('dispatch')
            ->once()
            ->with(Mockery::on(
                fn($event) => $event instanceof BeforeExecutionPlannedEvent
                && $event->job === $exampleJob
                && $event->job->getExecutionPlannedAt()?->getTimestamp() === (new DateTimeImmutable(self::FROZEN_DATE_TIME))->modify('+ 1800 seconds')->getTimestamp()
                && $event->delayInSeconds === 1800
                && $event->prefixedQueueName === $queueNamePrefix . $queueName
                && $event->plannedExecutionStrategy === PlannedExecutionStrategyEnum::SQS_DELIVERY_DELAY,
            ))
            ->andReturn($beforeExecutionPlannedEventMock);

        $eventDispatcherMock
            ->expects('dispatch')
            ->once()
            ->with(Mockery::on(fn($event) => $event instanceof AfterExecutionPlannedEvent
                && $event->job === $exampleJob
                && $event->job->getExecutionPlannedAt()?->getTimestamp() === (new DateTimeImmutable(self::FROZEN_DATE_TIME))->modify('+ 1800 seconds')->getTimestamp()
                && $event->delayInSeconds === 1800
                && $event->prefixedQueueName === $queueNamePrefix . $queueName
                && $event->plannedExecutionStrategy === PlannedExecutionStrategyEnum::SQS_DELIVERY_DELAY
                && $event->messageId === self::SQS_MESSAGE_ID
                && $event->scheduledEventId === null))
            ->andReturn($afterExecutionPlannedEventMock);

        $eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with(
                Mockery::on(fn($event) => $event instanceof BeforeMessageSentEvent
                && $event->job === $exampleJob
                && $event->delayInSeconds === 900
                && $event->prefixedQueueName === $queueNamePrefix . $queueName),
            )
            ->andReturn($beforeMessageSentEventMock);

        $eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn($event) => $event instanceof AfterMessageSentEvent
                && $event->delayInSeconds === 900
                && $event->messageAttributes === [
                    'customMessageAttribute' => [
                        'DataType' => 'String',
                        'StringValue' => 'customMessageAttributeValue',
                    ],
                    'QueueUrl' => [
                        'DataType' => 'String',
                        'StringValue' => $queueNamePrefix . $queueName,
                    ],
                ]
                && $event->messageBody === '{"jobUuid":"some-job-uuid","jobName":"exampleJob","attempts":1,"createdAt":"2018-08-01T10:15:47+01:00","jobParameters":{"foo":"bar"},"executionPlannedAt":"2016-08-15T15:30:00+00:00"}'))
            ->andReturn($afterMessageSentEventMock);

        $queueManager = $this->createQueueManagerWithExpectations($queueNamePrefix, 1, null, $eventDispatcherMock);

        $expectedMessageBody = [
            'jobUuid' => 'some-job-uuid',
            'jobName' => 'exampleJob',
            'attempts' => 1,
            'createdAt' => '2018-08-01T10:15:47+01:00',
            'jobParameters' => [
                'foo' => 'bar',
            ],
            'executionPlannedAt' => '2016-08-15T15:30:00+00:00',
        ];

        $this->sqsClientMock->expects('sendMessage')
            ->with(
                Mockery::on(
                    fn(array $message): bool => $this->messageCheckOk(
                        $message,
                        Json::encode($expectedMessageBody),
                        900,
                    ),
                ),
            )
            ->andReturn($this->createSqsSendMessageResultMock());

        $this->loggerMock->hasInfo(
            'Requested delay is greater than SQS limit. Job execution has been planned and will be requeued until then.',
        );
        $this->loggerMock->hasInfo('Job (exampleJob) [some-job-uuid] pushed into exampleJobQueue queue');

        $queueManager->pushDelayed($exampleJob, 1800);
    }


    #[DataProvider('queueNameDataProvider')]
    public function testPushDelayedWithJobDelayOverSqsMaxDelayLimitUsingDelayedJobScheduler(string $queueName, string $queueNamePrefix): void
    {
        $jobUuid = '86dac5fb-cd24-4f77-b3dd-409ebf5e4b9f';
        $exampleJob = $this->createExampleJob($queueName);

        /** @var DelayedJobSchedulerInterface&MockInterface $delayedJobSchedulerMock */
        $delayedJobSchedulerMock = Mockery::mock(DelayedJobSchedulerInterface::class);
        $fullQueueName = $queueNamePrefix . $queueName;
        $delayedJobSchedulerMock
            ->expects('scheduleJob')
            ->with($exampleJob, $fullQueueName)
            ->andReturn($jobUuid);
        $delayedJobSchedulerMock
            ->expects('getSchedulerName')
            ->andReturn('SQS Scheduler');

        $queueManager = $this->createQueueManagerWithExpectations(
            $queueNamePrefix,
            1,
            $delayedJobSchedulerMock,
        );

        $this->loggerMock->hasInfo(
            'Requested delay is greater than SQS limit. Job execution has been planned using SQS Scheduler.',
        );

        $queueManager->pushDelayed($exampleJob, 1800);
    }


    #[DataProvider('queueNameDataProvider')]
    public function testPushDelayedWithJobDelayOverCustomSqsMaxDelayLimitUsingDelayedJobScheduler(string $queueName, string $queueNamePrefix): void
    {
        $jobUuid = '86dac5fb-cd24-4f77-b3dd-409ebf5e4b9f';
        $exampleJob = $this->createExampleJob($queueName);

        /** @var DelayedJobSchedulerInterface&MockInterface $delayedJobSchedulerMock */
        $delayedJobSchedulerMock = Mockery::mock(DelayedJobSchedulerInterface::class);
        $fullQueueName = $queueNamePrefix . $queueName;
        $delayedJobSchedulerMock
            ->expects('scheduleJob')
            ->with($exampleJob, $fullQueueName)
            ->andReturn($jobUuid);
        $delayedJobSchedulerMock
            ->expects('getSchedulerName')
            ->andReturn('SQS Scheduler');

        $queueManager = $this->createQueueManagerWithExpectations(
            $queueNamePrefix,
            1,
            $delayedJobSchedulerMock,
        );

        $this->loggerMock->hasInfo(
            'Requested delay is greater than SQS limit. Job execution has been planned using SQS Scheduler.',
        );

        $queueManager->pushDelayed($exampleJob, 65, 60);
    }


    #[DataProvider('queueNameDataProvider')]
    public function testPushDelayedWithJobDelayOverCustomSqsMaxDelayLimitUsingDelayedJobSchedulerAndEventDispatcher(string $queueName, string $queueNamePrefix): void
    {
        $scheduledEventUuid = '86dac5fb-cd24-4f77-b3dd-409ebf5e4b9f';
        $exampleJob = $this->createExampleJob($queueName);

        /** @var DelayedJobSchedulerInterface&MockInterface $delayedJobSchedulerMock */
        $delayedJobSchedulerMock = Mockery::mock(DelayedJobSchedulerInterface::class);

        $fullQueueName = $queueNamePrefix . $queueName;
        $delayedJobSchedulerMock
            ->expects('scheduleJob')
            ->with($exampleJob, $fullQueueName)
            ->andReturn($scheduledEventUuid);
        $delayedJobSchedulerMock
            ->expects('getSchedulerName')
            ->andReturn('SQS Scheduler');

        /** @var BeforeExecutionPlannedEvent&MockInterface $beforeExecutionPlannedEventMock */
        $beforeExecutionPlannedEventMock = Mockery::mock(BeforeExecutionPlannedEvent::class);
        /** @var BeforeExecutionPlannedEvent&MockInterface $afterExecutionPlannedEventMock */
        $afterExecutionPlannedEventMock = Mockery::mock(AfterExecutionPlannedEvent::class);

        /** @var EventDispatcherInterface&MockInterface $eventDispatcherMock */
        $eventDispatcherMock = Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcherMock
            ->expects('dispatch')
            ->once()
            ->with(Mockery::on(
                fn($event) => $event instanceof BeforeExecutionPlannedEvent
                && $event->job === $exampleJob
                && $event->job->getExecutionPlannedAt()?->getTimestamp() === (new DateTimeImmutable(self::FROZEN_DATE_TIME))->modify('+ 65 seconds')->getTimestamp()
                && $event->delayInSeconds === 65
                && $event->prefixedQueueName === $fullQueueName
                && $event->plannedExecutionStrategy === PlannedExecutionStrategyEnum::DELAYED_JOB_SCHEDULER,
            ))
            ->andReturn($beforeExecutionPlannedEventMock);

        $eventDispatcherMock
            ->expects('dispatch')
            ->once()
            ->with(Mockery::on(fn($event) => $event instanceof AfterExecutionPlannedEvent
                && $event->job === $exampleJob
                && $event->job->getExecutionPlannedAt()?->getTimestamp() === (new DateTimeImmutable(self::FROZEN_DATE_TIME))->modify('+ 65 seconds')->getTimestamp()
                && $event->delayInSeconds === 65
                && $event->prefixedQueueName === $fullQueueName
                && $event->plannedExecutionStrategy === PlannedExecutionStrategyEnum::DELAYED_JOB_SCHEDULER
                && $event->messageId === null
                && $event->scheduledEventId === $scheduledEventUuid))
            ->andReturn($afterExecutionPlannedEventMock);

        $queueManager = $this->createQueueManagerWithExpectations(
            $queueNamePrefix,
            1,
            $delayedJobSchedulerMock,
            $eventDispatcherMock,
        );

        $this->loggerMock->hasInfo(
            'Requested delay is greater than SQS limit. Job execution has been planned using SQS Scheduler.',
        );

        $queueManager->pushDelayed($exampleJob, 65, 60);
    }


    #[DataProvider('queueNameDataProvider')]
    public function testPushDelayedWithMilliSeconds(string $queueName, string $queueNamePrefix): void
    {
        $queueManager = $this->createQueueManagerWithExpectations($queueNamePrefix);

        $exampleJob = $this->createExampleJob($queueName);

        $this->sqsClientMock->expects('sendMessage')
            ->with(
                Mockery::on(
                    fn(array $message): bool => $this->messageCheckOk($message, $exampleJob->toJson(), 5),
                ),
            )
            ->andReturn($this->createSqsSendMessageResultMock());

        $queueManager->pushDelayedWithMilliseconds($exampleJob, 5000);
    }


    #[DataProvider('queueNameDataProvider')]
    public function testPushWithReconnect(string $queueName, string $queueNamePrefix): void
    {
        $queueManager = $this->createQueueManagerWithExpectations($queueNamePrefix, 2);

        $this->loggerMock->hasInfo('Job (exampleJob) [some-job-uuid] pushed into exampleJobQueue queue');

        $exampleJob = $this->createExampleJob($queueName);

        $awsException = new AwsException('Some nasty error', $this->awsCommandMock);

        $this->sqsClientMock->expects('sendMessage')
            ->with(
                Mockery::on(
                    fn(array $message): bool => $this->messageCheckOk($message, $exampleJob->toJson(), 0),
                ),
            )
            ->andThrow($awsException);

        $this->sqsClientMock->expects('sendMessage')
            ->with(
                Mockery::on(
                    fn(array $message): bool => $this->messageCheckOk($message, $exampleJob->toJson(), 0),
                ),
            )
            ->andReturn($this->createSqsSendMessageResultMock());

        $this->loggerMock->hasWarning(
            'Reconnecting: Some nasty error',
        );

        $queueManager->push($exampleJob);
    }


    #[DataProvider('queueNameDataProvider')]
    public function testConsume(string $queueName, string $queueNamePrefix): void
    {
        $queueManager = $this->createQueueManagerWithExpectations($queueNamePrefix);

        $expectedCallback = static function (SqsMessage $message): void {
        };

        $messages = $this->getSampleSqsMessages();

        $this->awsResultMock->shouldReceive('get')
            ->with('Messages')
            ->andReturn($messages)
            ->once();

        $this->sqsClientMock->expects('receiveMessage')
            ->with([
                'AttributeNames' => ['All'],
                'MaxNumberOfMessages' => 10,
                'MessageAttributeNames' => ['All'],
                'QueueUrl' => self::QUEUE_URL,
                'WaitTimeSeconds' => 10,
            ])
            ->andReturns($this->awsResultMock);

        $queueManager->consumeMessages(
            $expectedCallback,
            $queueName,
            [
                SqsQueueManager::MAX_NUMBER_OF_MESSAGES => 10,
            ],
        );
    }


    #[DataProvider('queueNameDataProvider')]
    public function testConsumeWithReconnect(string $queueName, string $queueNamePrefix): void
    {
        $queueManager = $this->createQueueManagerWithExpectations($queueNamePrefix, 2);

        $expectedCallback = static function (SqsMessage $message): void {
        };

        $awsException = new AwsException('Some nasty error', $this->awsCommandMock);

        $this->sqsClientMock->expects('receiveMessage')
            ->with([
                'AttributeNames' => ['All'],
                'MaxNumberOfMessages' => 10,
                'MessageAttributeNames' => ['All'],
                'QueueUrl' => self::QUEUE_URL,
                'WaitTimeSeconds' => 10,
            ])
            ->andThrow($awsException);

        $messages = $this->getSampleSqsMessages();

        $this->awsResultMock->shouldReceive('get')
            ->with('Messages')
            ->andReturn($messages)
            ->once();

        $this->sqsClientMock->expects('receiveMessage')
            ->with([
                'AttributeNames' => ['All'],
                'MaxNumberOfMessages' => 10,
                'MessageAttributeNames' => ['All'],
                'QueueUrl' => self::QUEUE_URL,
                'WaitTimeSeconds' => 10,
            ])
            ->andReturns($this->awsResultMock);

        $this->loggerMock->hasWarning('AwsException: Some nasty error');

        $this->loggerMock->hasWarning('Reconnecting: Some nasty error');

        $queueManager->consumeMessages(
            $expectedCallback,
            $queueName,
            [
                SqsQueueManager::MAX_NUMBER_OF_MESSAGES => 10,
            ],
        );
    }


    /**
     * @return array<int, array<string, mixed>>
     */
    public static function queueNameDataProvider(): array
    {
        return [
            [
                'queueName' => self::QUEUE_URL,
                'queueNamePrefix' => '',
            ],
            [
                'queueName' => 'MyQueue1',
                'queueNamePrefix' => 'https://sqs.eu-central-1.amazonaws.com/583027123456/',
            ],
        ];
    }


    /**
     * @return array<int, array<string, mixed>>
     */
    private function getSampleSqsMessages(): array
    {
        return [
            [
                'MessageId' => 'c176f71b-ea77-4b0e-af6a-d76246d77057',
                'ReceiptHandle' => self::RECEIPT_HANDLE,
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
                        'StringValue' => self::QUEUE_URL,
                        'DataType' => 'String',
                    ],
                ],
            ],
        ];
    }


    /**
     * @param array<string, mixed> $message
     */
    private function messageCheckOk(
        array $message,
        string $messageBody,
        int $delay,
        bool $checkCustomAttribute = true
    ): bool {
        return $message['MessageBody'] === $messageBody
            && $message[SqsSendingMessageFields::DELAY_SECONDS] === $delay
            && $message[SqsSendingMessageFields::QUEUE_URL] === self::QUEUE_URL
            && $message[SqsSendingMessageFields::MESSAGE_ATTRIBUTES][SqsSendingMessageFields::QUEUE_URL]['StringValue'] === self::QUEUE_URL
            && (
                $checkCustomAttribute === false
                || $message[SqsSendingMessageFields::MESSAGE_ATTRIBUTES][self::CUSTOM_MESSAGE_ATTRIBUTE]['StringValue'] === self::CUSTOM_MESSAGE_ATTRIBUTE_VALUE
            );
    }


    private function createExampleJob(string $queueName): ExampleJob
    {
        return new ExampleJob(
            ExampleJobDefinition::create()
                ->withQueueName($queueName),
            'bar',
            self::CUSTOM_MESSAGE_ATTRIBUTES,
        );
    }


    private function createQueueManagerWithExpectations(
        string $queueNamePrefix = '',
        int $connectionIsCreatedTimes = 1,
        ?DelayedJobSchedulerInterface $delayedJobScheduler = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): SqsQueueManager {
        return new SqsQueueManager(
            self::S3_BUCKET_NAME,
            $this->createSqsClientFactoryMock($this->sqsClientMock, $connectionIsCreatedTimes),
            $this->createS3ClientFactoryMock($this->s3ClientMock, $connectionIsCreatedTimes),
            new TestOnlyMessageKeyGenerator(),
            $this->loggerMock,
            new FrozenDateTimeImmutableFactory(
                new DateTimeImmutable(self::FROZEN_DATE_TIME),
            ),
            $delayedJobScheduler,
            $eventDispatcher,
            1,
            $queueNamePrefix,
        );
    }


    /**
     * @return SqsClientFactory&MockInterface
     */
    private function createSqsClientFactoryMock(
        SqsClient $sqsClientMock,
        int $connectionIsCreatedTimes = 1
    ): SqsClientFactory {
        $sqsClientFactoryMock = Mockery::mock(SqsClientFactory::class);

        $sqsClientFactoryMock->expects('create')
            ->withNoArgs()
            ->times($connectionIsCreatedTimes)
            ->andReturns($sqsClientMock);

        return $sqsClientFactoryMock;
    }


    /**
     * @return S3ClientFactory&MockInterface
     */
    private function createS3ClientFactoryMock(
        S3Client $s3ClientMock,
        int $connectionIsCreatedTimes = 1
    ): S3ClientFactory {
        $s3ClientFactoryMock = Mockery::mock(S3ClientFactory::class);

        $s3ClientFactoryMock->expects('create')
            ->withNoArgs()
            ->times($connectionIsCreatedTimes)
            ->andReturns($s3ClientMock);

        return $s3ClientFactoryMock;
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

        $expectedMessageBody = '{"jobUuid":"uuid-123","jobName":"exampleSqsJob","attempts":1,"createdAt":"2022-04-21T14:05:47+00:00","jobParameters":{"foo":"bar"}}';

        $queueManager = $this->createQueueManagerWithExpectations();

        $this->s3ClientMock->expects('getObject')
            ->andReturns($this->awsResultMock);

        $this->awsResultMock->shouldReceive('get')
            ->once()
            ->with('Body')
            ->andReturn($expectedMessageBody);

        $this->loggerMock->hasWarning(
            'Message with ID 96819875-6e43-4a14-9652-6b5d239f5e1b will be downloaded from S3 bucket: dfo-webhooksender-s3. Key: de2710e6-56b8-47cc-95fe-5aae916ef2c8.json',
        );

        $sqsMessages = $queueManager->fromAwsResultMessages($messages, $queueUrl);

        Assert::assertCount(1, $sqsMessages);
        Assert::assertSame($expectedMessageBody, $sqsMessages[0]->getBody());
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

        $queueManager = $this->createQueueManagerWithExpectations();

        $this->s3ClientMock->allows('getObject')->never();

        $this->awsResultMock->shouldNotReceive('get');

        $this->loggerMock->hasWarning(
            'Message with ID 96819875-6e43-4a14-9652-6b5d239f5e1b will be downloaded from S3 bucket: dfo-webhooksender-s3. Key: de2710e6-56b8-47cc-95fe-5aae916ef2c8.json',
        );

        $sqsMessages = $queueManager->fromAwsResultMessages($messages, $queueUrl);

        Assert::assertCount(1, $sqsMessages);
        Assert::assertSame($messageBodyExpected, $sqsMessages[0]->getBody());
    }


    /**
     * @return Result<mixed>&MockInterface
     */
    private function createSqsSendMessageResultMock(): Result
    {
        $mock = Mockery::mock(Result::class);
        $mock->allows('get')
            ->with('MessageId')
            ->andReturn(self::SQS_MESSAGE_ID);

        return $mock;
    }
}
