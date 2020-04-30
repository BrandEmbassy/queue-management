<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\Execution\MaximumAttemptsExceededException;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\JobParameters;
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


    public function testInitializedData(): void
    {
        $jobCreatedAt = new DateTimeImmutable();

        $simpleJob = $this->createSimpleJob('bar', $jobCreatedAt);

        $expectedJobData = [
            JobParameters::UUID => DummyJob::UUID,
            JobParameters::JOB_NAME => DummyJob::JOB_NAME,
            JobParameters::ATTEMPTS => 1,
            JobParameters::CREATED_AT => $jobCreatedAt->format(DateTime::ATOM),
            JobParameters::PARAMETERS => ['foo' => 'bar'],
        ];

        self::assertEquals($jobCreatedAt, $simpleJob->getCreatedAt());
        self::assertEquals(JobInterface::INIT_ATTEMPTS, $simpleJob->getAttempts());
        self::assertEquals('bar', $simpleJob->getParameter('foo'));
        self::assertEquals(DummyJob::UUID, $simpleJob->getUuid());
        self::assertEquals(DummyJob::JOB_NAME, $simpleJob->getName());
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
            DummyJob::UUID,
            $jobCreatedAt,
            JobInterface::INIT_ATTEMPTS,
            DummyJobDefinition::create(),
            new ArrayCollection(['foo' => $foo])
        );
    }
}
