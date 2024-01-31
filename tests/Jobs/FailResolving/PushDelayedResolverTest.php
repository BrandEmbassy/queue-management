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
use Psr\Log\Test\TestLogger;
use Tests\BE\QueueManagement\Jobs\ExampleJob;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;

/**
 * @final
 */
class PushDelayedResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var QueueManagerInterface|MockInterface
     */
    private $queueManagerMock;

    private TestLogger $loggerMock;


    protected function setUp(): void
    {
        parent::setUp();
        $this->queueManagerMock = Mockery::mock(QueueManagerInterface::class);
        $this->loggerMock = new TestLogger();
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

        $this->loggerMock->hasWarning('Job requeued [delay: 5.000]');

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

        $this->loggerMock->hasWarning('Job requeued [delay: 3.500]');

        $pushDelayedResolver->resolve($exampleJob, new Exception());
    }


    private function createPushDelayedResolver(): PushDelayedResolver
    {
        return new PushDelayedResolver($this->queueManagerMock, $this->loggerMock);
    }
}
