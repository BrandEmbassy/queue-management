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

    public function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->jobExecutorMock = Mockery::mock(JobExecutorInterface::class);
        $this->pushDelayedResolverMock = Mockery::mock(PushDelayedResolver::class);
        $this->jobLoaderMock = Mockery::mock(JobLoaderInterface::class);
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

        /*$this->amqpChannelMock->shouldReceive('basic_ack')
            ->with(self::AMQP_TAG)
            ->once();*/

        $sqsMessage = [
            'DelaySeconds' => 0,
            'MessageAttributes' => [
                'QueueUrl' => [
                    'DataType' => 'String',
                    'StringValue' => self::DUMMY_QUEUE_URL 
                ]
            ],
            'MessageBody' => Json::encode(['foo' => 'bar']),
            'QueueUrl' => self::DUMMY_QUEUE_URL
        ];

        // $sqsMessage = $this->createSqsMessage(['a' => 'b']);

        $sqsConsumer = $this->createSqsConsumer();
        $sqsConsumer($sqsMessage);
    }


    /**
     * @param mixed[] $messageData
     */
    private function createSqsMessage(array $messageData): SqsMessage
    {
        $sqsMessage = new SqsMessage($messageData, self::DUMMY_QUEUE_URL);
        //$amqpMessage->setChannel($this->amqpChannelMock);
        //$amqpMessage->setDeliveryTag(self::AMQP_TAG);

        return $sqsMessage;
    }



    private function createSqsConsumer(): SqsConsumer
    {
        $sqsClient = new SqsClient([
            'region'  => 'eu-central-1',
            'version' => '2012-11-05',
            'http' => [
               'verify' => false,
            ]
        ]);

        return new SqsConsumer(
            $this->loggerMock,
            $this->jobExecutorMock,
            $this->pushDelayedResolverMock,
            $this->jobLoaderMock,
            $sqsClient
        );
    }    
}
