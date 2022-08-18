<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Queue\AWSSQS\SqsConsumer;
use BE\QueueManagement\Queue\AWSSQS\SqsQueueManager;
use BE\QueueManagement\Queue\QueueWorker;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;

/**
 * @final
 */
class SqsWorkerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var SqsQueueManager&MockInterface
     */
    private $sqsQueueManagerMock;

    /**
     * @var SqsConsumer&MockInterface
     */
    private $sqsConsumerMock;


    public function setUp(): void
    {
        parent::setUp();
        $this->sqsQueueManagerMock = Mockery::mock(SqsQueueManager::class);
        $this->sqsConsumerMock = Mockery::mock(SqsConsumer::class);
    }


    public function testStart(): void
    {
        $sqsWorker = $this->createSqsWorker();

        $customConsumeParameters = ['foo' => 'bar'];

        $this->sqsQueueManagerMock->shouldReceive('getConsumeLoopIterationsCount')
            ->withNoArgs()
            ->andReturn(1);

        $this->sqsQueueManagerMock->shouldReceive('consumeMessages')
            ->with($this->sqsConsumerMock, ExampleJobDefinition::QUEUE_NAME, $customConsumeParameters)
            ->once();

        $sqsWorker->start(ExampleJobDefinition::QUEUE_NAME, $customConsumeParameters);
    }


    private function createSqsWorker(): QueueWorker
    {
        return new QueueWorker($this->sqsQueueManagerMock, $this->sqsConsumerMock);
    }
}
