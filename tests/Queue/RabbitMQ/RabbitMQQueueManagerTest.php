<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Queue\RabbitMQ\ConnectionFactory;
use BE\QueueManagement\Queue\RabbitMQ\RabbitMQQueueManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\BE\QueueManagement\Jobs\ExampleJob;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;

/**
 * @final
 */
class RabbitMQQueueManagerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var ConnectionFactory&MockInterface
     */
    private $connectionFactoryMock;

    /**
     * @var LoggerInterface&MockInterface
     */
    private $loggerMock;

    /**
     * @var MockInterface&AMQPChannel
     */
    private $amqpChannelMock;

    /**
     * @var MockInterface&AMQPStreamConnection
     */
    private $amqpStreamConnectionMock;


    public function setUp(): void
    {
        parent::setUp();
        $this->connectionFactoryMock = Mockery::mock(ConnectionFactory::class);
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->amqpChannelMock = Mockery::mock(AMQPChannel::class);
        $this->amqpStreamConnectionMock = Mockery::mock(AMQPStreamConnection::class);
    }


    public function testPush(): void
    {
        $this->expectSetUpConnection();

        $this->loggerMock->shouldReceive('info')
            ->with('Job (exampleJob) [some-job-uud] pushed into exampleJobQueue queue')
            ->once();

        $exampleJob = $this->createExampleJob();

        $this->amqpChannelMock->shouldReceive('basic_publish')
            ->with(
                Mockery::on(
                    static function (AMQPMessage $message) use ($exampleJob): bool {
                        return $message->getBody() === $exampleJob->toJson()
                            && $message->get_properties()['delivery_mode'] === AMQPMessage::DELIVERY_MODE_PERSISTENT;
                    }
                ),
                ExampleJobDefinition::QUEUE_NAME . '.sync'
            )
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->push($exampleJob);
    }


    public function testPushDelayed(): void
    {
        $this->expectSetUpConnection();

        $exampleJob = $this->createExampleJob();

        $this->amqpChannelMock->shouldReceive('basic_publish')
            ->with(
                Mockery::on(
                    static function (AMQPMessage $message) use ($exampleJob): bool {
                        /** @var AMQPTable<mixed, mixed> $applicationHeaders */
                        $applicationHeaders = $message->get_properties()['application_headers'];

                        $expectedNativeData = ['x-delay' => 5000];

                        return $message->getBody() === $exampleJob->toJson()
                            && $message->get_properties()['delivery_mode'] === AMQPMessage::DELIVERY_MODE_PERSISTENT
                            && $applicationHeaders->getNativeData() === $expectedNativeData;
                    }
                ),
                ExampleJobDefinition::QUEUE_NAME . '.sync'
            )
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->pushDelayed($exampleJob, 5);
    }


    public function testPushDelayedWithMilliSeconds(): void
    {
        $this->expectSetUpConnection();

        $exampleJob = $this->createExampleJob();

        $this->amqpChannelMock->shouldReceive('basic_publish')
            ->with(
                Mockery::on(
                    static function (AMQPMessage $message) use ($exampleJob): bool {
                        /** @var AMQPTable<mixed, mixed> $applicationHeaders */
                        $applicationHeaders = $message->get_properties()['application_headers'];

                        $expectedNativeData = ['x-delay' => 500];

                        return $message->getBody() === $exampleJob->toJson()
                            && $message->get_properties()['delivery_mode'] === AMQPMessage::DELIVERY_MODE_PERSISTENT
                            && $applicationHeaders->getNativeData() === $expectedNativeData;
                    }
                ),
                ExampleJobDefinition::QUEUE_NAME . '.sync'
            )
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->pushDelayedWithMilliseconds($exampleJob, 500);
    }


    public function testPushWithReconnect(): void
    {
        $this->expectSetUpConnection(2, 2);

        $this->loggerMock->shouldReceive('info')
            ->with('Job (exampleJob) [some-job-uud] pushed into exampleJobQueue queue')
            ->once();

        $exampleJob = $this->createExampleJob();

        $this->amqpChannelMock->shouldReceive('basic_publish')
            ->with(
                Mockery::on(
                    static function (AMQPMessage $message) use ($exampleJob): bool {
                        return $message->getBody() === $exampleJob->toJson()
                            && $message->get_properties()['delivery_mode'] === AMQPMessage::DELIVERY_MODE_PERSISTENT;
                    }
                ),
                ExampleJobDefinition::QUEUE_NAME . '.sync'
            )
            ->once()
            ->andThrow(new AMQPRuntimeException('Broken pipe'));

        $this->amqpChannelMock->shouldReceive('basic_publish')
            ->with(
                Mockery::on(
                    static function (AMQPMessage $message) use ($exampleJob): bool {
                        return $message->getBody() === $exampleJob->toJson()
                            && $message->get_properties()['delivery_mode'] === AMQPMessage::DELIVERY_MODE_PERSISTENT;
                    }
                ),
                ExampleJobDefinition::QUEUE_NAME . '.sync'
            )
            ->once();

        $this->loggerMock->shouldReceive('warning')
            ->with(
                'Reconnecting: Broken pipe',
                Mockery::hasKey('queueName')
            )
            ->once();

        $this->amqpChannelMock->shouldReceive('close')
            ->withNoArgs()
            ->once();

        $this->amqpStreamConnectionMock->shouldReceive('close')
            ->withNoArgs()
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->push($exampleJob);
    }


    public function testConsume(): void
    {
        $this->expectSetUpConnection();

        $expectedCallback = function (AMQPMessage $message): void {
        };

        $this->amqpChannelMock->shouldReceive('basic_qos')
            ->with(0, 2, false)
            ->once();

        $this->amqpChannelMock->shouldReceive('basic_consume')
            ->with(ExampleJobDefinition::QUEUE_NAME, '', false, true, false, false, $expectedCallback)
            ->once();

        $this->amqpChannelMock->shouldReceive('close')
            ->withNoArgs()
            ->once();

        $this->amqpStreamConnectionMock->shouldReceive('close')
            ->withNoArgs()
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->consumeMessages(
            $expectedCallback,
            ExampleJobDefinition::QUEUE_NAME,
            [
                RabbitMQQueueManager::PREFETCH_COUNT => 2,
                RabbitMQQueueManager::NO_ACK => true,
            ]
        );
    }


    public function testConsumeWithReconnect(): void
    {
        $this->expectSetUpConnection(2, 2);

        $expectedCallback = function (AMQPMessage $message): void {
        };

        $amqpChannelMock = $this->amqpChannelMock;

        $amqpChannelMock->shouldReceive('basic_qos')
            ->with(0, 2, false)
            ->twice();

        $amqpChannelMock->shouldReceive('basic_consume')
            ->with(ExampleJobDefinition::QUEUE_NAME, '', false, true, false, false, $expectedCallback)
            ->twice();

        $callbackMock = static function (): void {
        };

        $amqpChannelMock->callbacks = [$callbackMock];
        $brokenPipeException = new AMQPRuntimeException('Broken pipe');
        $amqpChannelMock->shouldReceive('wait')
            ->once()
            ->andThrow($brokenPipeException);
        $amqpChannelMock->shouldReceive('wait')
            ->once()
            ->andReturnUsing(
                static function () use ($amqpChannelMock): void {
                    $amqpChannelMock->callbacks = [];
                }
            );

        $amqpChannelMock->shouldReceive('close')
            ->withNoArgs()
            ->times(2);

        $this->amqpStreamConnectionMock->shouldReceive('close')
            ->withNoArgs()
            ->times(2);

        $this->loggerMock->shouldReceive('warning')
            ->with('AMQPChannel disconnected: Broken pipe', ['exception' => $brokenPipeException])
            ->once();

        $this->loggerMock->shouldReceive('warning')
            ->with('Reconnecting: Broken pipe', Mockery::hasKey('queueName'))
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->consumeMessages(
            $expectedCallback,
            ExampleJobDefinition::QUEUE_NAME,
            [
                RabbitMQQueueManager::PREFETCH_COUNT => 2,
                RabbitMQQueueManager::NO_ACK => true,
            ]
        );
    }


    /**
     * @dataProvider connectionStatusDataProvider
     */
    public function testCheckConnection(bool $connectionStatus): void
    {
        $this->amqpStreamConnectionMock->shouldReceive('isConnected')
            ->withNoArgs()
            ->once()
            ->andReturn($connectionStatus);

        $this->connectionFactoryMock->shouldReceive('create')
            ->withNoArgs()
            ->once()
            ->andReturn($this->amqpStreamConnectionMock);

        $queueManager = $this->createQueueManager();

        Assert::assertSame($connectionStatus, $queueManager->checkConnection());
    }


    /**
     * @return bool[][]
     */
    public function connectionStatusDataProvider(): array
    {
        return [
            [true],
            [false],
        ];
    }


    private function createExampleJob(): ExampleJob
    {
        return new ExampleJob();
    }


    private function createQueueManager(): RabbitMQQueueManager
    {
        return new RabbitMQQueueManager($this->connectionFactoryMock, $this->loggerMock);
    }


    private function expectSetUpConnection(int $connectionIsCreatedTimes = 1, int $channelIsCreatedTimes = 1): void
    {
        $this->amqpStreamConnectionMock->shouldReceive('channel')
            ->withNoArgs()
            ->times($channelIsCreatedTimes)
            ->andReturn($this->amqpChannelMock);

        $this->connectionFactoryMock->shouldReceive('create')
            ->withNoArgs()
            ->times($connectionIsCreatedTimes)
            ->andReturn($this->amqpStreamConnectionMock);

        $this->amqpChannelMock->shouldReceive('queue_declare')
            ->with(ExampleJobDefinition::QUEUE_NAME, false, true, false, false, false, [])
            ->once();

        $this->amqpChannelMock->shouldReceive('exchange_declare')
            ->with(
                ExampleJobDefinition::QUEUE_NAME . '.sync',
                'x-delayed-message',
                false,
                true,
                false,
                false,
                false,
                ['x-delayed-type' => ['S', 'direct']]
            )
            ->once();

        $this->amqpChannelMock->shouldReceive('queue_bind')
            ->with(ExampleJobDefinition::QUEUE_NAME, ExampleJobDefinition::QUEUE_NAME . '.sync')
            ->once();
    }
}
