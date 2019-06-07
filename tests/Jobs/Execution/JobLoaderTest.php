<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\BlacklistedJobUuidException;
use BE\QueueManagement\Jobs\Execution\JobLoader;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionsContainer;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\JobTerminator;
use BE\QueueManagement\Jobs\Loading\SimpleJobLoader;
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

        $dummyJobDefinition = new DummyJobDefinition(new SimpleJobLoader());

        $this->jobDefinitionsContainerMock->shouldReceive('get')
            ->with(DummyJob::JOB_NAME)
            ->once()
            ->andReturn($dummyJobDefinition);

        $messageBodyData = [
            JobInterface::UUID       => DummyJob::UUID,
            JobInterface::ATTEMPTS   => DummyJob::ATTEMPTS,
            JobInterface::JOB_NAME   => DummyJob::JOB_NAME,
            JobInterface::CREATED_AT => DummyJob::CREATED_AT,
            JobInterface::PARAMETERS => [DummyJob::PARAMETER_FOO => 'bar'],
        ];

        /** @var DummyJob $simpleJob */
        $simpleJob = $jobLoader->loadJob(Json::encode($messageBodyData));

        self::assertEquals('bar', $simpleJob->getFoo());
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
            JobInterface::UUID     => DummyJob::UUID,
            JobInterface::ATTEMPTS => 31,
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
