<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Jobs\BlacklistedJobUuidException;
use BE\QueueManagement\Jobs\Execution\JobExecutorInterface;
use BE\QueueManagement\Jobs\Execution\JobLoaderInterface;
use BE\QueueManagement\Jobs\FailResolving\PushDelayedResolver;
use BE\QueueManagement\Jobs\JobDefinitions\UnknownJobDefinitionException;
use BE\QueueManagement\Queue\RabbitMQ\RabbitMQConsumer;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Nette\Utils\Json;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\BE\QueueManagement\Jobs\ExampleJob;

final class RabbitMQConsumerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const AMQP_TAG = 'someAmqpTag';

    /**
     * @var LoggerInterface|MockInterface
     */
    private $loggerMock;

    /**
     * @var JobExecutorInterface|MockInterface
     */
    private $jobExecutorMock;

    /**
     * @var PushDelayedResolver|MockInterface
     */
    private $pushDelayedResolverMock;

    /**
     * @var JobLoaderInterface|MockInterface
     */
    private $jobLoaderMock;

    /**
     * @var MockInterface|AMQPChannel
     */
    private $amqpChannelMock;


    public function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->jobExecutorMock = Mockery::mock(JobExecutorInterface::class);
        $this->pushDelayedResolverMock = Mockery::mock(PushDelayedResolver::class);
        $this->jobLoaderMock = Mockery::mock(JobLoaderInterface::class);
        $this->amqpChannelMock = Mockery::mock(AMQPChannel::class);
    }


    public function testSuccessExecution(): void
    {
        $exampleJob = new ExampleJob();

        $this->jobLoaderMock->shouldReceive('loadJob')
            ->with('{"a":"b"}')
            ->once()
            ->andReturn($exampleJob);

        $this->jobExecutorMock->shouldReceive('execute')
            ->with($exampleJob)
            ->once();

        $this->amqpChannelMock->shouldReceive('basic_ack')
            ->with(self::AMQP_TAG)
            ->once();

        $amqpMessage = $this->createAmqpMessage(['a' => 'b']);

        $rabbitMqConsumer = $this->createRabbitMqConsumer();
        $rabbitMqConsumer($amqpMessage);
    }


    public function testRequeueUnknownJobDefinition(): void
    {
        $unknownJobDefinitionException = UnknownJobDefinitionException::createFromUnknownJobName(ExampleJob::JOB_NAME);

        $this->jobLoaderMock->shouldReceive('loadJob')
            ->with('{"a":"b"}')
            ->once()
            ->andThrow($unknownJobDefinitionException);

        $this->loggerMock->shouldReceive('error')
            ->with(
                'Consumer failed, job requeued: Job definition (exampleJob) not found, maybe you forget to register it',
                ['exception' => $unknownJobDefinitionException]
            )
            ->once();

        $this->amqpChannelMock->shouldReceive('basic_reject')
            ->with(self::AMQP_TAG, true)
            ->once();

        $this->expectException(UnknownJobDefinitionException::class);
        $this->expectExceptionMessage('Job definition (exampleJob) not found, maybe you forget to register it');

        $amqpMessage = $this->createAmqpMessage(['a' => 'b']);

        $rabbitMqConsumer = $this->createRabbitMqConsumer();
        $rabbitMqConsumer($amqpMessage);
    }


    public function testRejectBlacklistedJob(): void
    {
        $blacklistedJobUuidException = BlacklistedJobUuidException::createFromJobUuid(ExampleJob::UUID);

        $this->jobLoaderMock->shouldReceive('loadJob')
            ->with('{"a":"b"}')
            ->once()
            ->andThrow($blacklistedJobUuidException);

        $this->loggerMock->shouldReceive('warning')
            ->with(
                'Job removed from queue: Job some-job-uud blacklisted',
                ['exception' => $blacklistedJobUuidException]
            )
            ->once();

        $this->amqpChannelMock->shouldReceive('basic_nack')
            ->with(self::AMQP_TAG)
            ->once();

        $amqpMessage = $this->createAmqpMessage(['a' => 'b']);

        $rabbitMqConsumer = $this->createRabbitMqConsumer();
        $rabbitMqConsumer($amqpMessage);
    }


    /**
     * @param mixed[] $messageData
     */
    private function createAmqpMessage(array $messageData): AMQPMessage
    {
        $amqpMessage = new AMQPMessage(Json::encode($messageData));
        $amqpMessage->setChannel($this->amqpChannelMock);
        $amqpMessage->setDeliveryTag(self::AMQP_TAG);

        return $amqpMessage;
    }


    private function createRabbitMqConsumer(): RabbitMQConsumer
    {
        return new RabbitMQConsumer(
            $this->loggerMock,
            $this->jobExecutorMock,
            $this->pushDelayedResolverMock,
            $this->jobLoaderMock
        );
    }
}
