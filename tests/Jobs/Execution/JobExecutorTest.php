<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\Execution\JobExecutor;
use BE\QueueManagement\Jobs\Execution\JobProcessorInterface;
use BE\QueueManagement\Jobs\Execution\UnableToProcessLoadedJobException;
use BE\QueueManagement\Jobs\JobDefinitions\UnknownJobDefinitionException;
use BrandEmbassy\DateTime\DateTimeImmutableFactory;
use DateTimeImmutable;
use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\BE\QueueManagement\Jobs\DummyJob;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\DummyJobDefinition;

class JobExecutorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var MockInterface|LoggerInterface
     */
    private $loggerMock;

    /**
     * @var DateTimeImmutableFactory|MockInterface
     */
    private $dateTimeImmutableFactory;


    public function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->dateTimeImmutableFactory = Mockery::mock(DateTimeImmutableFactory::class);
    }


    public function testExecutableJob(): void
    {
        $dummyJobDefinition = DummyJobDefinition::create()
            ->withJobProcessor(new DummyJobProcessor());

        $dummyJob = new DummyJob($dummyJobDefinition);

        $startedAt = new DateTimeImmutable();
        $executedAt = $startedAt->modify('+5 seconds');

        $this->dateTimeImmutableFactory->shouldReceive('getNow')
            ->withNoArgs()
            ->twice()
            ->andReturn($startedAt, $executedAt);

        $this->loggerMock->shouldReceive('info')
            ->with('Job execution start')
            ->once();

        $this->loggerMock->shouldReceive('info')
            ->with('Job execution success [5 sec]', ['executionTime' => 5])
            ->once();

        $jobExecutor = $this->createJobExecutor();
        $jobExecutor->execute($dummyJob);
    }


    public function testConsumerFailedExceptionThrown(): void
    {
        /** @var JobProcessorInterface|MockInterface $jobProcessorMock */
        $jobProcessorMock = Mockery::mock(JobProcessorInterface::class);

        $dummyJobDefinition = DummyJobDefinition::create()
            ->withJobProcessor($jobProcessorMock);

        $dummyJob = new DummyJob($dummyJobDefinition);

        $unknownJobDefinitionException = UnknownJobDefinitionException::createFromUnknownJobName('unknown');

        $jobProcessorMock->shouldReceive('process')
            ->with($dummyJob)
            ->once()
            ->andThrow($unknownJobDefinitionException);

        $startedAt = new DateTimeImmutable();

        $this->dateTimeImmutableFactory->shouldReceive('getNow')
            ->withNoArgs()
            ->once()
            ->andReturn($startedAt);

        $this->loggerMock->shouldReceive('info')
            ->with('Job execution start')
            ->once();

        $jobExecutor = $this->createJobExecutor();

        $this->expectException(UnknownJobDefinitionException::class);
        $this->expectExceptionMessage('Job definition (unknown) not found, maybe you forget to register it');

        $jobExecutor->execute($dummyJob);
    }


    public function testDuringProcessExceptionThrown(): void
    {
        /** @var JobProcessorInterface|MockInterface $jobProcessorMock */
        $jobProcessorMock = Mockery::mock(JobProcessorInterface::class);

        $dummyJobDefinition = DummyJobDefinition::create()
            ->withJobProcessor($jobProcessorMock);

        $dummyJob = new DummyJob($dummyJobDefinition);

        $someProcessException = new Exception('API not reachable');

        $jobProcessorMock->shouldReceive('process')
            ->with($dummyJob)
            ->once()
            ->andThrow($someProcessException);

        $startedAt = new DateTimeImmutable();

        $this->dateTimeImmutableFactory->shouldReceive('getNow')
            ->withNoArgs()
            ->once()
            ->andReturn($startedAt);

        $this->loggerMock->shouldReceive('info')
            ->with('Job execution start')
            ->once();

        $jobExecutor = $this->createJobExecutor();

        $this->expectException(UnableToProcessLoadedJobException::class);
        $this->expectExceptionMessage('API not reachable');

        $jobExecutor->execute($dummyJob);
    }


    private function createJobExecutor(): JobExecutor
    {
        return new JobExecutor($this->loggerMock, $this->dateTimeImmutableFactory);
    }
}