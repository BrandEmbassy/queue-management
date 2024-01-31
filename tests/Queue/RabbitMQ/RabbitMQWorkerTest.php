<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Queue\QueueWorker;
use BE\QueueManagement\Queue\RabbitMQ\RabbitMQConsumer;
use BE\QueueManagement\Queue\RabbitMQ\RabbitMQQueueManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;

/**
 * @final
 */
class RabbitMQWorkerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var RabbitMQQueueManager&MockInterface
     */
    private $rabbitMQQueueManagerMock;

    /**
     * @var RabbitMQConsumer&MockInterface
     */
    private $rabbitMQConsumerMock;


    protected function setUp(): void
    {
        parent::setUp();
        $this->rabbitMQQueueManagerMock = Mockery::mock(RabbitMQQueueManager::class);
        $this->rabbitMQConsumerMock = Mockery::mock(RabbitMQConsumer::class);
    }


    public function testStart(): void
    {
        $rabbitMQWorker = $this->createRabbitMqWorker();

        $customConsumeParameters = ['foo' => 'bar'];

        $this->rabbitMQQueueManagerMock->shouldReceive('consumeMessages')
            ->with($this->rabbitMQConsumerMock, ExampleJobDefinition::QUEUE_NAME, $customConsumeParameters)
            ->once();

        $rabbitMQWorker->start(ExampleJobDefinition::QUEUE_NAME, $customConsumeParameters);
    }


    private function createRabbitMqWorker(): QueueWorker
    {
        return new QueueWorker($this->rabbitMQQueueManagerMock, $this->rabbitMQConsumerMock);
    }
}
