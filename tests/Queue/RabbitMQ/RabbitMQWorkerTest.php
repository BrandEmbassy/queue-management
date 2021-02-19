<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Queue\RabbitMQ\RabbitMQConsumer;
use BE\QueueManagement\Queue\RabbitMQ\RabbitMQQueueManager;
use BE\QueueManagement\Queue\RabbitMQ\RabbitMQWorker;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;

class RabbitMQWorkerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var RabbitMQQueueManager|MockInterface
     */
    private $rabbitMQQueueManagerMock;

    /**
     * @var RabbitMQConsumer|MockInterface
     */
    private $rabbitMQConsumerMock;


    public function setUp(): void
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


    private function createRabbitMqWorker(): RabbitMQWorker
    {
        return new RabbitMQWorker($this->rabbitMQQueueManagerMock, $this->rabbitMQConsumerMock);
    }
}
