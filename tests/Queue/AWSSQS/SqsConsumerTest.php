<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Jobs\BlacklistedJobUuidException;
use BE\QueueManagement\Jobs\Execution\JobExecutorInterface;
use BE\QueueManagement\Jobs\Execution\JobLoaderInterface;
use BE\QueueManagement\Jobs\Execution\UnableToProcessLoadedJobException;
use BE\QueueManagement\Jobs\FailResolving\PushDelayedResolver;
use BE\QueueManagement\Jobs\JobDefinitions\UnknownJobDefinitionException;
use BE\QueueManagement\Queue\AWSSQS\SqsConsumer;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Nette\Utils\Json;
use Aws\Sqs\SqsClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\BE\QueueManagement\Jobs\ExampleJob;
use Tests\BE\QueueManagement\Jobs\Execution\ExampleWarningOnlyException;

final class SqsConsumerTest extends TestCase
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

    public function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->jobExecutorMock = Mockery::mock(JobExecutorInterface::class);
        $this->pushDelayedResolverMock = Mockery::mock(PushDelayedResolver::class);
        $this->jobLoaderMock = Mockery::mock(JobLoaderInterface::class);
        $this->sqsClientMock = Mockery::mock(SqsClient::class);
    }


    public function testSuccessExecution(): void
    {
        $exampleJob = new ExampleJob();

        $this->jobLoaderMock->shouldReceive('loadJob')
            ->with('{"foo":"bar"}')
            ->once()
            ->andReturn($exampleJob);

        $this->jobExecutorMock->shouldReceive('execute')
            ->with($exampleJob)
            ->once();

        $this->sqsClientMock->shouldReceive('deleteMessage')
            ->with([
                'QueueUrl' => self::DUMMY_QUEUE_URL ,
                'ReceiptHandle' => self::DUMMY_RECEIPT_HANDLE
            ])
            ->once();

        $sqsMessageData = [
            'MessageAttributes' => [
                'QueueUrl' => [
                    'DataType' => 'String',
                    'StringValue' => self::DUMMY_QUEUE_URL 
                ]
            ],
            'Body' => Json::encode(['foo' => 'bar']),
            'ReceiptHandle' => self::DUMMY_RECEIPT_HANDLE
        ];

        $sqsMessage = new SqsMessage($sqsMessageData, self::DUMMY_QUEUE_URL);

        $sqsConsumer = $this->createSqsConsumer($this->sqsClientMock);
        $sqsConsumer($sqsMessage);
    }

    private function createSqsConsumer(SqsClient $sqsClient): SqsConsumer
    {
        return new SqsConsumer(
            $this->loggerMock,
            $this->jobExecutorMock,
            $this->pushDelayedResolverMock,
            $this->jobLoaderMock,
            $sqsClient
        );
    }    
}
