<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Queue\RabbitMQ\ConnectionFactory;
use BE\QueueManagement\Queue\RabbitMQ\RabbitMQQueueManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\BE\QueueManagement\Jobs\DummyJob;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\DummyJobDefinition;

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
            ->with('Job (dummyJob) [some-job-uud] pushed into dummyJobQueue queue')
            ->once();

        $dummyJob = $this->createDummyJob();

        $this->amqpChannelMock->shouldReceive('basic_publish')
            ->with(
                Mockery::on(
                    static function (AMQPMessage $message) use ($dummyJob): bool {
                        return $message->getBody() === $dummyJob->toJson()
                            && $message->get_properties()['delivery_mode'] === AMQPMessage::DELIVERY_MODE_PERSISTENT;
                    }
                ),
                DummyJobDefinition::QUEUE_NAME . '.sync'
            )
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->push($dummyJob);
    }


    public function testPushDelayed(): void
    {
        $this->expectSetUpConnection();

        $dummyJob = $this->createDummyJob();

        $this->amqpChannelMock->shouldReceive('basic_publish')
            ->with(
                Mockery::on(
                    static function (AMQPMessage $message) use ($dummyJob): bool {
                        /** @var AMQPTable<mixed, mixed> $applicationHeaders */
                        $applicationHeaders = $message->get_properties()['application_headers'];

                        $expectedNativeData = ['x-delay' => 5000];

                        return $message->getBody() === $dummyJob->toJson()
                            && $message->get_properties()['delivery_mode'] === AMQPMessage::DELIVERY_MODE_PERSISTENT
                            && $applicationHeaders->getNativeData() === $expectedNativeData;
                    }
                ),
                DummyJobDefinition::QUEUE_NAME . '.sync'
            )
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->pushDelayed($dummyJob, 5000);
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
            ->with(DummyJobDefinition::QUEUE_NAME, '', false, true, false, false, $expectedCallback)
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
            DummyJobDefinition::QUEUE_NAME,
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

        self::assertEquals($connectionStatus, $queueManager->checkConnection());
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


    private function createDummyJob(): DummyJob
    {
        return new DummyJob();
    }


    private function createQueueManager(): RabbitMQQueueManager
    {
        return new RabbitMQQueueManager($this->connectionFactoryMock, $this->loggerMock);
    }


    private function expectSetUpConnection(): void
    {
        $this->amqpStreamConnectionMock->shouldReceive('channel')
            ->withNoArgs()
            ->once()
            ->andReturn($this->amqpChannelMock);

        $this->connectionFactoryMock->shouldReceive('create')
            ->withNoArgs()
            ->once()
            ->andReturn($this->amqpStreamConnectionMock);

        $this->amqpChannelMock->shouldReceive('queue_declare')
            ->with(DummyJobDefinition::QUEUE_NAME, false, true, false, false, false, [])
            ->once();

        $this->amqpChannelMock->shouldReceive('exchange_declare')
            ->with(
                DummyJobDefinition::QUEUE_NAME . '.sync',
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
            ->with(DummyJobDefinition::QUEUE_NAME, DummyJobDefinition::QUEUE_NAME . '.sync')
            ->once();
    }
}
