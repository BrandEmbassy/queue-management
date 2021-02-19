<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\FailResolving;

use BE\QueueManagement\Jobs\FailResolving\DelayRules\ConstantDelayRule;
use BE\QueueManagement\Jobs\FailResolving\PushDelayedResolver;
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
            ->withDelayRule(new ConstantDelayRule(5));

        $exampleJob = new ExampleJob($exampleJobDefinition);

        $pushDelayedResolver = $this->createPushDelayedResolver();

        $this->queueManagerMock->shouldReceive('pushDelayedWithMilliSeconds')
            ->with($exampleJob, 5000)
            ->once();

        $this->loggerMock->shouldReceive('warning')
            ->with('Job requeued [delay: 5.000]')
            ->once();

        $pushDelayedResolver->resolve($exampleJob, new Exception());
    }


    public function testPushDelayedInMilliSeconds(): void
    {
        $exampleJobDefinition = ExampleJobDefinition::create()
            ->withDelayRule(new ConstantDelayRuleWithMilliseconds(3500));

        $exampleJob = new ExampleJob($exampleJobDefinition);

        $pushDelayedResolver = $this->createPushDelayedResolver();

        $this->queueManagerMock->shouldReceive('pushDelayedWithMilliSeconds')
            ->with($exampleJob, 3500)
            ->once();

        $this->loggerMock->shouldReceive('warning')
            ->with('Job requeued [delay: 3.500]')
            ->once();

        $pushDelayedResolver->resolve($exampleJob, new Exception());
    }


    private function createPushDelayedResolver(): PushDelayedResolver
    {
        return new PushDelayedResolver($this->queueManagerMock, $this->loggerMock);
    }
}
