<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\JobTerminator;
use BE\QueueManagement\Jobs\JobUuidBlacklistInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class JobTerminatorTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    private const MINIMUM_ATTEMPTS = 20;

    /**
     * @var JobUuidBlacklistInterface|MockInterface
     */
    private $jobUuidBlacklistMock;

    /**
     * @var MockInterface|LoggerInterface
     */
    private $loggerMock;


    public function setUp(): void
    {
        parent::setUp();
        $this->jobUuidBlacklistMock = Mockery::mock(JobUuidBlacklistInterface::class);
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
    }


    public function testShouldNotBeTerminated(): void
    {
        $jobTerminator = $this->createJobTerminator();

        self::assertFalse($jobTerminator->shouldBeTerminated('some-uuid', 20));
        self::assertFalse($jobTerminator->shouldBeTerminated('some-uuid', 19));
        self::assertFalse($jobTerminator->shouldBeTerminated('some-uuid', 1));
    }


    public function testShouldBeTerminated(): void
    {
        $jobTerminator = $this->createJobTerminator();

        $this->jobUuidBlacklistMock->shouldReceive('contains')
            ->with('some-uuid')
            ->once()
            ->andReturnTrue();

        self::assertTrue($jobTerminator->shouldBeTerminated('some-uuid', 21));
    }


    public function testTermination(): void
    {
        $jobTerminator = $this->createJobTerminator();

        $this->jobUuidBlacklistMock->shouldReceive('remove')
            ->with('some-uuid')
            ->once();

        $this->loggerMock->shouldReceive('warning')
            ->with('Job some-uuid was terminated')
            ->once();

        $jobTerminator->terminate('some-uuid');
    }


    public function createJobTerminator(): JobTerminator
    {
        return new JobTerminator(self::MINIMUM_ATTEMPTS, $this->jobUuidBlacklistMock, $this->loggerMock);
    }
}
