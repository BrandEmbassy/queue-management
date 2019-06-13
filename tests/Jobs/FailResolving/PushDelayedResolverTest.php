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
use Tests\BE\QueueManagement\Jobs\DummyJob;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\DummyJobDefinition;

class PushDelayedResolverTest extends TestCase
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


    public function testPushDelayed(): void
    {
        $dummyJobDefinition = DummyJobDefinition::create()
            ->withDelayRule(new ConstantDelayRule(5));

        $dummyJob = new DummyJob($dummyJobDefinition);

        $pushDelayedResolver = $this->createPushDelayedResolver();

        $this->queueManagerMock->shouldReceive('pushDelayed')
            ->with($dummyJob, 5)
            ->once();

        $this->loggerMock->shouldReceive('warning')
            ->with('Job requeued [delay: 5]')
            ->once();

        $pushDelayedResolver->resolve($dummyJob, new Exception());
    }


    private function createPushDelayedResolver(): PushDelayedResolver
    {
        return new PushDelayedResolver($this->queueManagerMock, $this->loggerMock);
    }
}
