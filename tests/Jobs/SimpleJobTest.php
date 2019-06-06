<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\Execution\MaximumAttemptsExceededException;
use BE\QueueManagement\Jobs\JobValidationException;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\DummyJobDefinition;

class SimpleJobTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    private const JOB_UUID = 'some-random-uuid';


    public function testInitializedData(): void
    {
        $jobCreatedAt = new DateTimeImmutable();

        $dummyJob = $this->createDummyJob('bar', $jobCreatedAt);

        $expectedJobData = [
            'jobUuid'       => self::JOB_UUID,
            'jobName'       => DummyJob::JOB_NAME,
            'attempts'      => 0,
            'createdAt'     => $jobCreatedAt->format(DateTime::ATOM),
            'jobParameters' => ['foo' => 'bar'],
        ];

        self::assertEquals($jobCreatedAt, $dummyJob->getCreatedAt());
        self::assertEquals(0, $dummyJob->getAttempts());
        self::assertEquals('bar', $dummyJob->getFoo());
        self::assertEquals(self::JOB_UUID, $dummyJob->getUuid());
        self::assertEquals(DummyJob::JOB_NAME, $dummyJob->getName());
        self::assertEquals(DummyJobDefinition::MAX_ATTEMPTS, $dummyJob->getMaxAttempts());
        self::assertEquals(Json::encode($expectedJobData), $dummyJob->toJson());
    }


    public function testReturnExecutionStartedAt(): void
    {
        $startedAt = new DateTimeImmutable();

        $dummyJob = $this->createDummyJob('bar', new DateTimeImmutable());

        $dummyJob->executionStarted($startedAt);

        self::assertEquals($startedAt, $dummyJob->getExecutionStartedAt());
    }


    public function testThrowMaximumAttemptsExceededException(): void
    {
        $dummyJob = $this->createDummyJob('bar', new DateTimeImmutable());
        $dummyJob->incrementAttempts();
        $dummyJob->incrementAttempts();

        self::assertEquals(2, $dummyJob->getAttempts());

        $dummyJob->incrementAttempts();

        $this->expectException(MaximumAttemptsExceededException::class);
        $this->expectExceptionMessage('Maximum limit (3) attempts exceeded');

        $dummyJob->incrementAttempts();
    }


    public function testThrowUnknownParameterException(): void
    {
        $dummyJob = $this->createDummyJob('bar', new DateTimeImmutable());

        $this->expectException(JobValidationException::class);
        $this->expectExceptionMessage('Parameter unknown not found, available parameters: foo');

        $dummyJob->getParameter('unknown');
    }


    private function createDummyJob(string $foo, DateTimeImmutable $jobCreatedAt): DummyJob
    {
        return new DummyJob(
            self::JOB_UUID,
            $jobCreatedAt,
            0,
            new DummyJobDefinition(),
            new ArrayCollection([DummyJob::PARAMETER_FOO => $foo])
        );
    }
}
