<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use Aws\Sqs\SqsClient;
use BE\QueueManagement\Jobs\BlacklistedJobUuidException;
use BE\QueueManagement\Jobs\Execution\JobExecutorInterface;
use BE\QueueManagement\Jobs\Execution\JobLoaderInterface;
use BE\QueueManagement\Jobs\Execution\UnableToProcessLoadedJobException;
use BE\QueueManagement\Jobs\Execution\UnresolvableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\FailResolving\PushDelayedResolver;
use BE\QueueManagement\Jobs\JobDefinitions\UnknownJobDefinitionException;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Queue\AWSSQS\MessageDeduplication\MessageDeduplication;
use BE\QueueManagement\Queue\AWSSQS\MessageDeduplication\MessageDeduplicationDisabled;
use BE\QueueManagement\Queue\AWSSQS\SqsConsumer;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttribute;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttributeDataType;
use BE\QueueManagement\Queue\AWSSQS\SqsQueueManager;
use BE\QueueManagement\Queue\JobExecutionStatus;
use BE\QueueManagement\Queue\QueueManagerInterface;
use BrandEmbassy\DateTime\FrozenDateTimeImmutableFactory;
use DateTimeImmutable;
use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Nette\Utils\Json;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Tests\BE\QueueManagement\Jobs\ExampleJob;
use Tests\BE\QueueManagement\Jobs\Execution\ExampleExceptionWithCustomLogLevel;
use Tests\BE\QueueManagement\Jobs\Execution\ExampleExceptionWithPreviousCustomLogLevelException;
use Tests\BE\QueueManagement\Jobs\Execution\ExampleExceptionWithPreviousWarningOnlyException;
use Tests\BE\QueueManagement\Jobs\Execution\ExampleWarningOnlyException;
use Throwable;

/**
 * @phpstan-import-type TSqsMessage from SqsMessage
 * @final
 */
class SqsConsumerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const QUEUE_URL = 'https://sqs.eu-central-1.amazonaws.com/583027123456/MyQueue1';

    private const RECEIPT_HANDLE = '123456777';

    private const MESSAGE_ID = 'c176f71b-ea77-4b0e-af6a-d76246d77057';

    private const FROZEN_DATE_TIME = '2016-08-15T15:00:00+00:00';

    private TestLogger $loggerMock;

    /**
     * @var JobExecutorInterface&MockInterface
     */
    private $jobExecutorMock;

    /**
     * @var PushDelayedResolver&MockInterface
     */
    private $pushDelayedResolverMock;

    /**
     * @var JobLoaderInterface|MockInterface
     */
    private $jobLoaderMock;

    /**
     * @var SqsClient&MockInterface
     */
    private $sqsClientMock;

    private MessageDeduplication $messageDeduplicationDisabled;

    private FrozenDateTimeImmutableFactory $frozenDateTimeImmutableFactory;

    /**
     * @var QueueManagerInterface&MockInterface
     */
    private $queueManagerMock;


    protected function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = new TestLogger();
        $this->jobExecutorMock = Mockery::mock(JobExecutorInterface::class);
        $this->pushDelayedResolverMock = Mockery::mock(PushDelayedResolver::class);
        $this->jobLoaderMock = Mockery::mock(JobLoaderInterface::class);
        $this->sqsClientMock = Mockery::mock(SqsClient::class);
        $this->messageDeduplicationDisabled = new MessageDeduplicationDisabled();
        $this->frozenDateTimeImmutableFactory = new FrozenDateTimeImmutableFactory(
            new DateTimeImmutable(self::FROZEN_DATE_TIME),
        );
        $this->queueManagerMock = Mockery::mock(QueueManagerInterface::class);
    }


    public function testSuccessExecution(): void
    {
        $exampleJob = new ExampleJob();

        $this->jobLoaderMock->expects('loadJob')
            ->with('{"foo":"bar"}')
            ->andReturn($exampleJob);

        $this->jobExecutorMock->expects('execute')
            ->with($exampleJob);

        $this->sqsClientMock->expects('deleteMessage')
            ->with([
                'QueueUrl' => self::QUEUE_URL,
                'ReceiptHandle' => self::RECEIPT_HANDLE,
            ]);

        $sqsMessage = $this->createSqsMessage($this->getSqsMessageData());
        $sqsConsumer = $this->createSqsConsumer($this->sqsClientMock);

        Assert::assertSame(JobExecutionStatus::SUCCESS, $sqsConsumer($sqsMessage));
    }


    public function testRequeueUnknownJobDefinition(): void
    {
        $unknownJobDefinitionException = UnknownJobDefinitionException::createFromUnknownJobName(ExampleJob::JOB_NAME);

        $this->jobLoaderMock->expects('loadJob')
            ->with('{"foo":"bar"}')
            ->andThrow($unknownJobDefinitionException);

        $this->loggerMock->hasError(
            'Consumer failed, job requeued: Job definition (exampleJob) not found, maybe you forget to register it',
        );

        $this->sqsClientMock->allows('deleteMessage')->never();

        $this->expectException(UnknownJobDefinitionException::class);
        $this->expectExceptionMessage('Job definition (exampleJob) not found, maybe you forget to register it');

        $sqsMessage = $this->createSqsMessage($this->getSqsMessageData());
        $sqsConsumer = $this->createSqsConsumer($this->sqsClientMock);

        $sqsConsumer($sqsMessage);
    }


    public function testRejectBlacklistedJob(): void
    {
        $blacklistedJobUuidException = BlacklistedJobUuidException::createFromJobUuid(ExampleJob::UUID);

        $this->jobLoaderMock->expects('loadJob')
            ->with('{"foo":"bar"}')
            ->andThrow($blacklistedJobUuidException);

        $this->loggerMock->hasWarning(
            'Job removed from queue: Job some-job-uuid blacklisted',
        );

        $this->sqsClientMock->expects('deleteMessage')
            ->with([
                'QueueUrl' => self::QUEUE_URL,
                'ReceiptHandle' => self::RECEIPT_HANDLE,
            ]);

        $sqsMessage = $this->createSqsMessage($this->getSqsMessageData());
        $sqsConsumer = $this->createSqsConsumer($this->sqsClientMock);

        Assert::assertSame(JobExecutionStatus::FAILED_UNRESOLVABLE, $sqsConsumer($sqsMessage));
    }


    public function testRemoveJobWithUnresolvableExceptionFailure(): void
    {
        $unresolvableProcessFailException = new class() extends Exception implements UnresolvableProcessFailExceptionInterface {
        };

        $this->jobLoaderMock->expects('loadJob')
            ->with('{"foo":"bar"}')
            ->andThrow(new $unresolvableProcessFailException('Unresolvable process failure.'));

        $this->loggerMock->hasWarning(
            'Job removed from queue: Unresolvable process failure.',
        );

        $this->sqsClientMock->expects('deleteMessage')
            ->with([
                'QueueUrl' => self::QUEUE_URL,
                'ReceiptHandle' => self::RECEIPT_HANDLE,
            ]);

        $sqsMessage = $this->createSqsMessage($this->getSqsMessageData());
        $sqsConsumer = $this->createSqsConsumer($this->sqsClientMock);

        Assert::assertSame(JobExecutionStatus::FAILED_UNRESOLVABLE, $sqsConsumer($sqsMessage));
    }


    public function testRequeueDelayableProcessFail(): void
    {
        $exampleJob = new ExampleJob();
        $unableToProcessLoadedJobException = new UnableToProcessLoadedJobException(
            $exampleJob,
            'Unable to process loaded job',
        );

        $this->jobLoaderMock->expects('loadJob')
            ->with('{"foo":"bar"}')
            ->andReturns($exampleJob);

        $this->jobExecutorMock->expects('execute')
            ->with($exampleJob)
            ->andThrow($unableToProcessLoadedJobException);

        $this->sqsClientMock->expects('deleteMessage')
            ->with([
                'QueueUrl' => self::QUEUE_URL,
                'ReceiptHandle' => self::RECEIPT_HANDLE,
            ]);

        $this->loggerMock->hasError(
            'Job execution failed [attempts: 1], reason: Unable to process loaded job',
        );

        $this->pushDelayedResolverMock->expects('resolve')
            ->with($exampleJob, $unableToProcessLoadedJobException);

        $sqsMessage = $this->createSqsMessage($this->getSqsMessageData());
        $sqsConsumer = $this->createSqsConsumer($this->sqsClientMock);

        Assert::assertSame(JobExecutionStatus::FAILED_DELAYED, $sqsConsumer($sqsMessage));
    }


    #[DataProvider('possibleLogLevelAlteringExceptionsThrownDataProvider')]
    public function testRequeueDelayableProcessFailWithLogLevelControl(Throwable $thrownException, JobInterface $job, callable $loggerExpectationCallable): void
    {
        $this->jobLoaderMock->expects('loadJob')
            ->with('{"foo":"bar"}')
            ->andReturns($job);

        $this->jobExecutorMock->expects('execute')
            ->with($job)
            ->andThrow($thrownException);

        $this->sqsClientMock->expects('deleteMessage')
            ->with([
                'QueueUrl' => self::QUEUE_URL,
                'ReceiptHandle' => self::RECEIPT_HANDLE,
            ]);

        $loggerExpectationCallable($this->loggerMock);

        $this->pushDelayedResolverMock->expects('resolve')
            ->with($job, $thrownException);

        $sqsMessage = $this->createSqsMessage($this->getSqsMessageData());
        $sqsConsumer = $this->createSqsConsumer($this->sqsClientMock);

        Assert::assertSame(JobExecutionStatus::FAILED_DELAYED, $sqsConsumer($sqsMessage));
    }


    /**
     * @return mixed[]
     */
    public static function possibleLogLevelAlteringExceptionsThrownDataProvider(): array
    {
        return [
            'exception is warning only' => (static function (): array {
                $exampleJob = new ExampleJob();

                return [
                    'thrownException' => ExampleWarningOnlyException::create($exampleJob),
                    'job' => $exampleJob,
                    'loggerExpectationCallable' => static fn(TestLogger $logger) => $logger->hasWarning('Job execution failed [attempts: 1], reason: I will be logged as a warning'),
                ];
            })(),
            'previous of the exception is warning only' => (static function (): array {
                $exampleJob = new ExampleJob();

                return [
                    'thrownException' => ExampleExceptionWithPreviousWarningOnlyException::create($exampleJob),
                    'job' => $exampleJob,
                    'loggerExpectationCallable' => static fn(TestLogger $logger) => $logger->hasWarning('Job execution failed [attempts: 1], reason: I will be logged as a warning'),
                ];
            })(),
            'exception is custom log level' => (static function (): array {
                $exampleJob = new ExampleJob();

                return [
                    'thrownException' => ExampleExceptionWithCustomLogLevel::create($exampleJob),
                    'job' => $exampleJob,
                    'loggerExpectationCallable' => static fn(TestLogger $logger) => $logger->hasInfo('Job execution failed [attempts: 1], reason: I will be logged as a info'),
                ];
            })(),
            'previous of the exception is custom log level' => (static function (): array {
                $exampleJob = new ExampleJob();

                return [
                    'thrownException' => ExampleExceptionWithPreviousCustomLogLevelException::create($exampleJob),
                    'job' => $exampleJob,
                    'loggerExpectationCallable' => static fn(TestLogger $logger) => $logger->hasInfo('Job execution failed [attempts: 1], reason: I will be logged as a info'),
                ];
            })(),
        ];
    }


    #[DataProvider('executionDelayedPlannedAtDataProvider')]
    public function testDelayJobWithExecutionPlannedAt(DateTimeImmutable $executionPlannedAt, int $expectedDelay): void
    {
        $exampleJob = new ExampleJob();
        $exampleJob->setExecutionPlannedAt($executionPlannedAt);

        $this->jobLoaderMock->expects('loadJob')
            ->with('{"foo":"bar"}')
            ->andReturn($exampleJob);

        $this->queueManagerMock->expects('pushDelayed')
            ->with($exampleJob, $expectedDelay, SqsQueueManager::MAX_DELAY_IN_SECONDS);

        $this->sqsClientMock->expects('deleteMessage')
            ->with([
                'QueueUrl' => self::QUEUE_URL,
                'ReceiptHandle' => self::RECEIPT_HANDLE,
            ]);

        $this->loggerMock->hasInfo(
            "Job requeued, it's not planned to be executed yet. [delay: 7200]",
        );

        $sqsMessage = $this->createSqsMessage($this->getSqsMessageData());
        $sqsConsumer = $this->createSqsConsumer($this->sqsClientMock);

        Assert::assertSame(JobExecutionStatus::DELAYED_NOT_PLANNED_YET, $sqsConsumer($sqsMessage));
    }


    /**
     * @return mixed[]
     */
    public static function executionDelayedPlannedAtDataProvider(): array
    {
        return [
            'Delayed two hours' => [
                'executionPlannedAt' => new DateTimeImmutable('2016-08-15T17:00:00+00:00'),
                'expectedDelay' => 7200,
            ],
            'Delayed one second' => [
                'executionPlannedAt' => new DateTimeImmutable('2016-08-15T15:00:01+00:0'),
                'expectedDelay' => 1,
            ],
            'Delayed different time zone' => [
                'executionPlannedAt' => new DateTimeImmutable('2016-08-15T20:00:00+02:0'),
                'expectedDelay' => 10800,
            ],
        ];
    }


    #[DataProvider('executionPlannedAtDataProvider')]
    public function testExecuteJobWithExecutionPlannedAt(DateTimeImmutable $executionPlannedAt): void
    {
        $exampleJob = new ExampleJob();
        $exampleJob->setExecutionPlannedAt($executionPlannedAt);

        $this->jobLoaderMock->expects('loadJob')
            ->with('{"foo":"bar"}')
            ->andReturn($exampleJob);

        $this->jobExecutorMock->expects('execute')
            ->with($exampleJob);

        $this->sqsClientMock->expects('deleteMessage')
            ->with([
                'QueueUrl' => self::QUEUE_URL,
                'ReceiptHandle' => self::RECEIPT_HANDLE,
            ]);

        $sqsMessage = $this->createSqsMessage($this->getSqsMessageData());
        $sqsConsumer = $this->createSqsConsumer($this->sqsClientMock);

        Assert::assertSame(JobExecutionStatus::SUCCESS, $sqsConsumer($sqsMessage));
    }


    /**
     * @return mixed[]
     */
    public static function executionPlannedAtDataProvider(): array
    {
        return [
            'One our late' => [
                'executionPlannedAt' => new DateTimeImmutable('2016-08-15T14:00:00+00:00'),
            ],
            'Same dateTime' => [
                'executionPlannedAt' => new DateTimeImmutable('2016-08-15T15:00:00+00:0'),
            ],
        ];
    }


    /**
     * @return TSqsMessage
     */
    private function getSqsMessageData(): array
    {
        return [
            'MessageId' => self::MESSAGE_ID,
            'Attributes' => [],
            'MessageAttributes' => [
                'QueueUrl' => new SqsMessageAttribute(
                    'QueueUrl',
                    self::QUEUE_URL,
                    SqsMessageAttributeDataType::STRING,
                ),
            ],
            'Body' => Json::encode(['foo' => 'bar']),
            'ReceiptHandle' => self::RECEIPT_HANDLE,
        ];
    }


    /**
     * @param TSqsMessage $messageData
     */
    private function createSqsMessage(array $messageData): SqsMessage
    {
        return new SqsMessage($messageData, self::QUEUE_URL);
    }


    private function createSqsConsumer(SqsClient $sqsClient): SqsConsumer
    {
        return new SqsConsumer(
            $this->loggerMock,
            $this->jobExecutorMock,
            $this->pushDelayedResolverMock,
            $this->jobLoaderMock,
            $sqsClient,
            $this->messageDeduplicationDisabled,
            $this->frozenDateTimeImmutableFactory,
            $this->queueManagerMock,
        );
    }
}
