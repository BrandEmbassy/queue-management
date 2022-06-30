<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use Aws\Sqs\SqsClient;
use BE\QueueManagement\Jobs\BlacklistedJobUuidException;
use BE\QueueManagement\Jobs\Execution\JobExecutorInterface;
use BE\QueueManagement\Jobs\Execution\JobLoaderInterface;
use BE\QueueManagement\Jobs\Execution\UnableToProcessLoadedJobException;
use BE\QueueManagement\Jobs\FailResolving\PushDelayedResolver;
use BE\QueueManagement\Jobs\JobDefinitions\UnknownJobDefinitionException;
use BE\QueueManagement\Queue\AWSSQS\MessageDeduplication\MessageDeduplication;
use BE\QueueManagement\Queue\AWSSQS\MessageDeduplication\MessageDeduplicationDisabled;
use BE\QueueManagement\Queue\AWSSQS\SqsConsumer;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\BE\QueueManagement\Jobs\ExampleJob;
use Tests\BE\QueueManagement\Jobs\Execution\ExampleWarningOnlyException;

/**
 * @final
 */
class SqsConsumerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public const DUMMY_QUEUE_URL = 'https://sqs.eu-central-1.amazonaws.com/583027123456/MyQueue1';
    public const DUMMY_RECEIPT_HANDLE = '123456777';

    /**
     * @var LoggerInterface&MockInterface
     */
    private $loggerMock;

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


    public function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->jobExecutorMock = Mockery::mock(JobExecutorInterface::class);
        $this->pushDelayedResolverMock = Mockery::mock(PushDelayedResolver::class);
        $this->jobLoaderMock = Mockery::mock(JobLoaderInterface::class);
        $this->sqsClientMock = Mockery::mock(SqsClient::class);
        $this->messageDeduplicationDisabled = new MessageDeduplicationDisabled();
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
                'QueueUrl' => self::DUMMY_QUEUE_URL ,
                'ReceiptHandle' => self::DUMMY_RECEIPT_HANDLE,
            ]);

        $sqsMessage = $this->createSqsMessage($this->getSqsMessageData());
        $sqsConsumer = $this->createSqsConsumer($this->sqsClientMock);
        $sqsConsumer($sqsMessage);
    }


    public function testRequeueUnknownJobDefinition(): void
    {
        $unknownJobDefinitionException = UnknownJobDefinitionException::createFromUnknownJobName(ExampleJob::JOB_NAME);

        $this->jobLoaderMock->expects('loadJob')
            ->with('{"foo":"bar"}')
            ->andThrow($unknownJobDefinitionException);

        $this->loggerMock->expects('error')
            ->with(
                'Consumer failed, job requeued: Job definition (exampleJob) not found, maybe you forget to register it',
                ['exception' => $unknownJobDefinitionException],
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

        $this->loggerMock->expects('warning')
            ->with(
                'Job removed from queue: Job some-job-uud blacklisted',
                ['exception' => $blacklistedJobUuidException],
            );

        $this->sqsClientMock->expects('deleteMessage')
            ->with([
                'QueueUrl' => self::DUMMY_QUEUE_URL ,
                'ReceiptHandle' => self::DUMMY_RECEIPT_HANDLE,
            ]);

        $sqsMessage = $this->createSqsMessage($this->getSqsMessageData());
        $sqsConsumer = $this->createSqsConsumer($this->sqsClientMock);
        $sqsConsumer($sqsMessage);
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
                'QueueUrl' => self::DUMMY_QUEUE_URL ,
                'ReceiptHandle' => self::DUMMY_RECEIPT_HANDLE,
            ]);

        $this->loggerMock->expects('error')
            ->with(
                'Job execution failed [attempts: 1], reason: Unable to process loaded job',
                [
                    'exception' => $unableToProcessLoadedJobException,
                    'previousException' => null,
                ],
            );

        $this->pushDelayedResolverMock->expects('resolve')
            ->with($exampleJob, $unableToProcessLoadedJobException);

        $sqsMessage = $this->createSqsMessage($this->getSqsMessageData());
        $sqsConsumer = $this->createSqsConsumer($this->sqsClientMock);
        $sqsConsumer($sqsMessage);
    }


    public function testRequeueDelayableProcessFailWarningOnly(): void
    {
        $exampleJob = new ExampleJob();
        $exampleWarningOnlyException = ExampleWarningOnlyException::create($exampleJob);

        $this->jobLoaderMock->expects('loadJob')
            ->with('{"foo":"bar"}')
            ->andReturns($exampleJob);

        $this->jobExecutorMock->expects('execute')
            ->with($exampleJob)
            ->andThrow($exampleWarningOnlyException);

        $this->sqsClientMock->expects('deleteMessage')
            ->with([
                'QueueUrl' => self::DUMMY_QUEUE_URL ,
                'ReceiptHandle' => self::DUMMY_RECEIPT_HANDLE,
            ]);

        $this->loggerMock->expects('warning')
            ->with(
                'Job execution failed [attempts: 1], reason: I will be logged as a warning',
                [
                    'exception' => $exampleWarningOnlyException,
                    'previousException' => null,
                ],
            );

        $this->pushDelayedResolverMock->expects('resolve')
            ->with($exampleJob, $exampleWarningOnlyException);

        $sqsMessage = $this->createSqsMessage($this->getSqsMessageData());
        $sqsConsumer = $this->createSqsConsumer($this->sqsClientMock);
        $sqsConsumer($sqsMessage);
    }


    /**
     * @return array<string, mixed>
     */
    private function getSqsMessageData(): array
    {
        return [
            'MessageAttributes' => [
                'QueueUrl' => [
                    'DataType' => 'String',
                    'StringValue' => self::DUMMY_QUEUE_URL,
                ],
            ],
            'Body' => Json::encode(['foo' => 'bar']),
            'ReceiptHandle' => self::DUMMY_RECEIPT_HANDLE,
        ];
    }


    /**
     * @param mixed[] $messageData
     */
    private function createSqsMessage(array $messageData): SqsMessage
    {
        return new SqsMessage($messageData, self::DUMMY_QUEUE_URL);
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
        );
    }
}
