<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\BlacklistedJobUuidException;
use BE\QueueManagement\Jobs\Execution\JobLoader;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionsContainer;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\JobTerminator;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;

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


    public function testTerminateJob(): void
    {
        $jobLoader = $this->createJobLoader();

        $this->jobTerminatorMock->shouldReceive('shouldBeTerminated')
            ->with('blacklisted-uuid', 31)
            ->once()
            ->andReturnTrue();

        $this->jobTerminatorMock->shouldReceive('terminate')
            ->with('blacklisted-uuid')
            ->once();

        $messageBodyData = [
            JobInterface::UUID     => 'blacklisted-uuid',
            JobInterface::ATTEMPTS => 31,
        ];

        $this->expectException(BlacklistedJobUuidException::class);
        $this->expectExceptionMessage('Job blacklisted-uuid blacklisted');

        $jobLoader->loadJob(Json::encode($messageBodyData));
    }


    private function createJobLoader(): JobLoader
    {
        return new JobLoader($this->jobDefinitionsContainerMock, $this->jobTerminatorMock);
    }
}
