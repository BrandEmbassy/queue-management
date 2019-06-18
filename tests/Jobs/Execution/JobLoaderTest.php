<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\BlacklistedJobUuidException;
use BE\QueueManagement\Jobs\Execution\JobLoader;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionsContainer;
use BE\QueueManagement\Jobs\JobParameters;
use BE\QueueManagement\Jobs\JobTerminator;
use BE\QueueManagement\Jobs\Loading\SimpleJobLoader;
use BE\QueueManagement\Jobs\SimpleJob;
use DateTime;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Tests\BE\QueueManagement\Jobs\DummyJob;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\DummyJobDefinition;

class JobLoaderTest extends TestCase
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
            ->with(DummyJob::UUID, DummyJob::ATTEMPTS)
            ->once()
            ->andReturnFalse();

        $dummyJobDefinition = DummyJobDefinition::create(DummyJob::JOB_NAME, SimpleJob::class)
            ->withJobLoader(new SimpleJobLoader());

        $this->jobDefinitionsContainerMock->shouldReceive('get')
            ->with(DummyJob::JOB_NAME)
            ->once()
            ->andReturn($dummyJobDefinition);

        $messageBodyData = [
            JobParameters::UUID       => DummyJob::UUID,
            JobParameters::ATTEMPTS   => DummyJob::ATTEMPTS,
            JobParameters::JOB_NAME   => DummyJob::JOB_NAME,
            JobParameters::CREATED_AT => DummyJob::CREATED_AT,
            JobParameters::PARAMETERS => [DummyJob::PARAMETER_FOO => 'bar'],
        ];

        /** @var SimpleJob $simpleJob */
        $simpleJob = $jobLoader->loadJob(Json::encode($messageBodyData));

        self::assertEquals('bar', $simpleJob->getParameter('foo'));
        self::assertEquals(DummyJob::UUID, $simpleJob->getUuid());
        self::assertEquals(DummyJob::JOB_NAME, $simpleJob->getName());
        self::assertEquals(DummyJobDefinition::MAX_ATTEMPTS, $simpleJob->getMaxAttempts());
        self::assertEquals(DummyJob::ATTEMPTS, $simpleJob->getAttempts());
        self::assertEquals(DummyJob::CREATED_AT, $simpleJob->getCreatedAt()->format(DateTime::ATOM));
        self::assertEquals($dummyJobDefinition, $simpleJob->getJobDefinition());
    }


    public function testTerminateJob(): void
    {
        $jobLoader = $this->createJobLoader();

        $this->jobTerminatorMock->shouldReceive('shouldBeTerminated')
            ->with(DummyJob::UUID, 31)
            ->once()
            ->andReturnTrue();

        $this->jobTerminatorMock->shouldReceive('terminate')
            ->with(DummyJob::UUID)
            ->once();

        $messageBodyData = [
            JobParameters::UUID     => DummyJob::UUID,
            JobParameters::ATTEMPTS => 31,
        ];

        $this->expectException(BlacklistedJobUuidException::class);
        $this->expectExceptionMessage('Job ' . DummyJob::UUID . ' blacklisted');

        $jobLoader->loadJob(Json::encode($messageBodyData));
    }


    private function createJobLoader(): JobLoader
    {
        return new JobLoader($this->jobDefinitionsContainerMock, $this->jobTerminatorMock);
    }
}
