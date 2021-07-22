<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\FailResolving;

use BE\QueueManagement\Jobs\FailResolving\FailResolveStrategy\ConstantDelayFailResolveStrategy;
use BE\QueueManagement\Jobs\FailResolving\FailResolveStrategy\DifferentQueueFailResolveStrategy;
use BE\QueueManagement\Jobs\FailResolving\JobFailResolver;
use BE\QueueManagement\Queue\QueueManagerInterface;
use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\BE\QueueManagement\Jobs\ExampleJob;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;

final class PushDelayedResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var QueueManagerInterface|MockInterface
     */
    private $queueManagerMock;

    /**
     * @var MockInterface|LoggerInterface
     */
    private $loggerMock;


    public function setUp(): void
    {
        parent::setUp();
        $this->queueManagerMock = Mockery::mock(QueueManagerInterface::class);
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
    }


    public function testPushDelayedInSeconds(): void
    {
        $exampleJobDefinition = ExampleJobDefinition::create()
            ->withFailResolveStrategy(new ConstantDelayFailResolveStrategy(5));

        $exampleJob = new ExampleJob($exampleJobDefinition);

        $pushDelayedResolver = $this->createPushDelayedResolver();

        $this->queueManagerMock->shouldReceive('push')
            ->with($exampleJob, 5000, 'exampleJobQueue')
            ->once();

        $this->loggerMock->shouldReceive('warning')
            ->with('Job requeued [delay: 5.000s]')
            ->once();

        $pushDelayedResolver->resolve($exampleJob, new Exception());
    }


    public function testPushToDifferentQueue(): void
    {
        $exampleJobDefinition = ExampleJobDefinition::create()
            ->withFailResolveStrategy(new DifferentQueueFailResolveStrategy('new-queue-name'));

        $exampleJob = new ExampleJob($exampleJobDefinition);

        $pushDelayedResolver = $this->createPushDelayedResolver();

        $this->queueManagerMock->shouldReceive('push')
            ->with($exampleJob, 0, 'new-queue-name')
            ->once();

        $this->loggerMock->shouldReceive('warning')
            ->with('Job requeued [delay: 0.000s]')
            ->once();

        $pushDelayedResolver->resolve($exampleJob, new Exception());
    }


    private function createPushDelayedResolver(): JobFailResolver
    {
        return new JobFailResolver($this->queueManagerMock, $this->loggerMock);
    }
}
