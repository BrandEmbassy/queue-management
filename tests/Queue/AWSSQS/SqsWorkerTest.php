<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Jobs\JobDefinitions\PrefixedQueueNameStrategy;
use BE\QueueManagement\Jobs\JobDefinitions\QueueNameStrategy;
use BE\QueueManagement\Queue\AWSSQS\SqsConsumer;
use BE\QueueManagement\Queue\AWSSQS\SqsQueueManager;
use BE\QueueManagement\Queue\QueueWorker;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
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


    protected function setUp(): void
    {
        parent::setUp();
        $this->sqsQueueManagerMock = Mockery::mock(SqsQueueManager::class);
        $this->sqsConsumerMock = Mockery::mock(SqsConsumer::class);
    }


    #[DataProvider('differentQueueNameStrategyDataProvider')]
    public function testStart(string $expectedQueueName, ?QueueNameStrategy $queueNameStrategy): void
    {
        $sqsWorker = $this->createSqsWorker($queueNameStrategy);

        $customConsumeParameters = ['foo' => 'bar'];

        $this->sqsQueueManagerMock->shouldReceive('getConsumeLoopIterationsCount')
            ->withNoArgs()
            ->andReturn(1);

        $this->sqsQueueManagerMock->shouldReceive('consumeMessages')
            ->with($this->sqsConsumerMock, $expectedQueueName, $customConsumeParameters)
            ->once();

        $sqsWorker->start(ExampleJobDefinition::QUEUE_NAME, $customConsumeParameters);
    }


    /**
     * @return mixed[][]
     */
    public static function differentQueueNameStrategyDataProvider(): array
    {
        return [
            'default' => [
                'expectedQueueName' => ExampleJobDefinition::QUEUE_NAME,
                'queueNameStrategy' => null,
            ],
            'prefixed' => [
                'expectedQueueName' => 'test_' . ExampleJobDefinition::QUEUE_NAME,
                'queueNameStrategy' => new PrefixedQueueNameStrategy('test_'),
            ],
        ];
    }


    private function createSqsWorker(?QueueNameStrategy $queueNameStrategy): QueueWorker
    {
        return new QueueWorker($this->sqsQueueManagerMock, $this->sqsConsumerMock, $queueNameStrategy);
    }
}
