<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\Execution\MaximumAttemptsExceededException;
use BE\QueueManagement\Jobs\JobValidationException;
use BE\QueueManagement\Jobs\SimpleJob;
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

        $simpleJob = $this->createSimpleJob('bar', $jobCreatedAt);

        $expectedJobData = [
            'jobUuid'       => self::JOB_UUID,
            'jobName'       => SimpleJob::JOB_NAME,
            'attempts'      => 0,
            'createdAt'     => $jobCreatedAt->format(DateTime::ATOM),
            'jobParameters' => ['foo' => 'bar'],
        ];

        self::assertEquals($jobCreatedAt, $simpleJob->getCreatedAt());
        self::assertEquals(0, $simpleJob->getAttempts());
        self::assertEquals('bar', $simpleJob->getParameter('foo'));
        self::assertEquals(self::JOB_UUID, $simpleJob->getUuid());
        self::assertEquals(SimpleJob::JOB_NAME, $simpleJob->getName());
        self::assertEquals(DummyJobDefinition::MAX_ATTEMPTS, $simpleJob->getMaxAttempts());
        self::assertEquals(Json::encode($expectedJobData), $simpleJob->toJson());
    }


    public function testReturnExecutionStartedAt(): void
    {
        $startedAt = new DateTimeImmutable();

        $simpleJob = $this->createSimpleJob('bar', new DateTimeImmutable());

        $simpleJob->executionStarted($startedAt);

        self::assertEquals($startedAt, $simpleJob->getExecutionStartedAt());
    }


    public function testThrowMaximumAttemptsExceededException(): void
    {
        $simpleJob = $this->createSimpleJob('bar', new DateTimeImmutable());
        $simpleJob->incrementAttempts();
        $simpleJob->incrementAttempts();

        self::assertEquals(2, $simpleJob->getAttempts());

        $simpleJob->incrementAttempts();

        $this->expectException(MaximumAttemptsExceededException::class);
        $this->expectExceptionMessage('Maximum limit (3) attempts exceeded');

        $simpleJob->incrementAttempts();
    }


    public function testThrowUnknownParameterException(): void
    {
        $simpleJob = $this->createSimpleJob('bar', new DateTimeImmutable());

        $this->expectException(JobValidationException::class);
        $this->expectExceptionMessage('Parameter unknown not found, available parameters: foo');

        $simpleJob->getParameter('unknown');
    }


    private function createSimpleJob(string $foo, DateTimeImmutable $jobCreatedAt): SimpleJob
    {
        return new SimpleJob(
            self::JOB_UUID,
            $jobCreatedAt,
            0,
            DummyJobDefinition::create(SimpleJob::JOB_NAME),
            new ArrayCollection(['foo' => $foo])
        );
    }
}
