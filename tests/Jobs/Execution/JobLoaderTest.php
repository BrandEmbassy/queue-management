<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\BlacklistedJobUuidException;
use BE\QueueManagement\Jobs\Execution\JobLoader;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionsContainer;
use BE\QueueManagement\Jobs\JobParameters;
use BE\QueueManagement\Jobs\JobTerminator;
use BE\QueueManagement\Jobs\Loading\SimpleJobLoader;
use BE\QueueManagement\Jobs\SimpleJob;
use BrandEmbassy\MockeryTools\DateTime\DateTimeAssertions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Nette\Utils\Json;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Tests\BE\QueueManagement\Jobs\ExampleJob;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;

final class JobLoaderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var JobDefinitionsContainer|MockInterface
     */
    private $jobDefinitionsContainerMock;

    /**
     * @var JobTerminator|MockInterface
     */
    private $jobTerminatorMock;


    public function setUp(): void
    {
        parent::setUp();
        $this->jobDefinitionsContainerMock = Mockery::mock(JobDefinitionsContainer::class);
        $this->jobTerminatorMock = Mockery::mock(JobTerminator::class);
    }


    public function testLoadSimpleJob(): void
    {
        $jobLoader = $this->createJobLoader();

        $this->jobTerminatorMock->shouldReceive('shouldBeTerminated')
            ->with(ExampleJob::UUID, ExampleJob::ATTEMPTS)
            ->once()
            ->andReturnFalse();

        $dummyJobDefinition = ExampleJobDefinition::create(ExampleJob::JOB_NAME, SimpleJob::class)
            ->withJobLoader(new SimpleJobLoader());

        $this->jobDefinitionsContainerMock->shouldReceive('get')
            ->with(ExampleJob::JOB_NAME)
            ->once()
            ->andReturn($dummyJobDefinition);

        $messageBodyData = [
            JobParameters::UUID => ExampleJob::UUID,
            JobParameters::ATTEMPTS => ExampleJob::ATTEMPTS,
            JobParameters::JOB_NAME => ExampleJob::JOB_NAME,
            JobParameters::CREATED_AT => ExampleJob::CREATED_AT,
            JobParameters::PARAMETERS => [ExampleJob::PARAMETER_FOO => 'bar'],
        ];

        /** @var SimpleJob $simpleJob */
        $simpleJob = $jobLoader->loadJob(Json::encode($messageBodyData));

        Assert::assertSame('bar', $simpleJob->getParameter('foo'));
        Assert::assertSame(ExampleJob::UUID, $simpleJob->getUuid());
        Assert::assertSame(ExampleJob::JOB_NAME, $simpleJob->getName());
        Assert::assertSame(ExampleJobDefinition::MAX_ATTEMPTS, $simpleJob->getMaxAttempts());
        Assert::assertSame(ExampleJob::ATTEMPTS, $simpleJob->getAttempts());
        DateTimeAssertions::assertDateTimeAtomEqualsDateTime(ExampleJob::CREATED_AT, $simpleJob->getCreatedAt());
        Assert::assertSame($dummyJobDefinition, $simpleJob->getJobDefinition());
    }


    public function testTerminateJob(): void
    {
        $jobLoader = $this->createJobLoader();

        $this->jobTerminatorMock->shouldReceive('shouldBeTerminated')
            ->with(ExampleJob::UUID, 31)
            ->once()
            ->andReturnTrue();

        $this->jobTerminatorMock->shouldReceive('terminate')
            ->with(ExampleJob::UUID)
            ->once();

        $messageBodyData = [
            JobParameters::UUID => ExampleJob::UUID,
            JobParameters::ATTEMPTS => 31,
        ];

        $this->expectException(BlacklistedJobUuidException::class);
        $this->expectExceptionMessage('Job ' . ExampleJob::UUID . ' blacklisted');

        $jobLoader->loadJob(Json::encode($messageBodyData));
    }


    private function createJobLoader(): JobLoader
    {
        return new JobLoader($this->jobDefinitionsContainerMock, $this->jobTerminatorMock);
    }
}
