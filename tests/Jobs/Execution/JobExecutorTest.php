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
use Psr\Log\Test\TestLogger;
use Tests\BE\QueueManagement\Jobs\ExampleJob;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;

/**
 * @final
 */
class JobExecutorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private TestLogger $loggerMock;

    /**
     * @var DateTimeImmutableFactory&MockInterface
     */
    private $dateTimeImmutableFactory;


    public function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = new TestLogger();
        $this->dateTimeImmutableFactory = Mockery::mock(DateTimeImmutableFactory::class);
    }


    public function testExecutableJob(): void
    {
        $exampleJobDefinition = ExampleJobDefinition::create()
            ->withJobProcessor(new ExampleJobProcessor());

        $exampleJob = new ExampleJob($exampleJobDefinition);
        $startedAt = new DateTimeImmutable();

        $this->dateTimeImmutableFactory->shouldReceive('getNow')
            ->withNoArgs()
            ->once()
            ->andReturn($startedAt);

        $this->loggerMock->hasInfo('Job execution start');
        $this->loggerMock->hasInfo('Job execution success');

        $jobExecutor = $this->createJobExecutor();
        $jobExecutor->execute($exampleJob);
    }


    public function testConsumerFailedExceptionThrown(): void
    {
        /** @var JobProcessorInterface|MockInterface $jobProcessorMock */
        $jobProcessorMock = Mockery::mock(JobProcessorInterface::class);

        $exampleJobDefinition = ExampleJobDefinition::create()
            ->withJobProcessor($jobProcessorMock);

        $exampleJob = new ExampleJob($exampleJobDefinition);

        $unknownJobDefinitionException = UnknownJobDefinitionException::createFromUnknownJobName('unknown');

        $jobProcessorMock->shouldReceive('process')
            ->with($exampleJob)
            ->once()
            ->andThrow($unknownJobDefinitionException);

        $startedAt = new DateTimeImmutable();

        $this->dateTimeImmutableFactory->shouldReceive('getNow')
            ->withNoArgs()
            ->once()
            ->andReturn($startedAt);

        $this->loggerMock->hasInfo('Job execution start');

        $jobExecutor = $this->createJobExecutor();

        $this->expectException(UnknownJobDefinitionException::class);
        $this->expectExceptionMessage('Job definition (unknown) not found, maybe you forget to register it');

        $jobExecutor->execute($exampleJob);
    }


    public function testDuringProcessExceptionThrown(): void
    {
        /** @var JobProcessorInterface|MockInterface $jobProcessorMock */
        $jobProcessorMock = Mockery::mock(JobProcessorInterface::class);

        $exampleJobDefinition = ExampleJobDefinition::create()
            ->withJobProcessor($jobProcessorMock);

        $exampleJob = new ExampleJob($exampleJobDefinition);

        $someProcessException = new Exception('API not reachable');

        $jobProcessorMock->shouldReceive('process')
            ->with($exampleJob)
            ->once()
            ->andThrow($someProcessException);

        $startedAt = new DateTimeImmutable();

        $this->dateTimeImmutableFactory->shouldReceive('getNow')
            ->withNoArgs()
            ->once()
            ->andReturn($startedAt);

        $this->loggerMock->hasInfo('Job execution start');

        $jobExecutor = $this->createJobExecutor();

        $this->expectException(UnableToProcessLoadedJobException::class);
        $this->expectExceptionMessage('API not reachable');

        $jobExecutor->execute($exampleJob);
    }


    private function createJobExecutor(): JobExecutor
    {
        return new JobExecutor($this->loggerMock, $this->dateTimeImmutableFactory);
    }
}
