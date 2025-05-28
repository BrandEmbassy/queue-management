<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\Execution\MaximumAttemptsExceededException;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\JobParameters;
use BE\QueueManagement\Jobs\JobValidationException;
use BE\QueueManagement\Jobs\SimpleJob;
use BrandEmbassy\DateTime\DateTimeFromString;
use BrandEmbassy\MockeryTools\DateTime\DateTimeAssertions;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nette\Utils\Json;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;

/**
 * @final
 */
class ExampleJobTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const JOB_CREATED_AT = '2020-12-02T05:16:45+00:00';


    public function testInitializedData(): void
    {
        $jobCreatedAt = DateTimeFromString::create(self::JOB_CREATED_AT);
        $simpleJob = $this->createSimpleJob('bar', $jobCreatedAt);

        $expectedJobData = [
            JobParameters::UUID => ExampleJob::UUID,
            JobParameters::JOB_NAME => ExampleJob::JOB_NAME,
            JobParameters::ATTEMPTS => 1,
            JobParameters::CREATED_AT => self::JOB_CREATED_AT,
            JobParameters::PARAMETERS => [
                'foo' => 'bar',
            ],
            JobParameters::EXECUTION_PLANNED_AT => null,
        ];

        DateTimeAssertions::assertDateTimeAtomEqualsDateTime(self::JOB_CREATED_AT, $simpleJob->getCreatedAt());
        Assert::assertSame(JobInterface::INIT_ATTEMPTS, $simpleJob->getAttempts());
        Assert::assertSame('bar', $simpleJob->getParameter('foo'));
        Assert::assertSame(ExampleJob::UUID, $simpleJob->getUuid());
        Assert::assertSame(ExampleJob::JOB_NAME, $simpleJob->getName());
        Assert::assertSame(ExampleJobDefinition::MAX_ATTEMPTS, $simpleJob->getMaxAttempts());
        Assert::assertSame(Json::encode($expectedJobData), $simpleJob->toJson());
    }


    public function testReturnExecutionStartedAt(): void
    {
        $startedAt = new DateTimeImmutable();

        $simpleJob = $this->createSimpleJob('bar', new DateTimeImmutable());

        $simpleJob->executionStarted($startedAt);

        Assert::assertSame($startedAt, $simpleJob->getExecutionStartedAt());
    }


    public function testThrowMaximumAttemptsExceededException(): void
    {
        $simpleJob = $this->createSimpleJob('bar', new DateTimeImmutable());
        $simpleJob->incrementAttempts();

        Assert::assertSame(2, $simpleJob->getAttempts());

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
        /**
         * Prevent phpstan error Template type T on class Doctrine\Common\Collections\Collection is not covariant
         * @var array<string,mixed>
         */
        $parameters = ['foo' => $foo];

        return new SimpleJob(
            ExampleJob::UUID,
            $jobCreatedAt,
            JobInterface::INIT_ATTEMPTS,
            ExampleJobDefinition::create(),
            new ArrayCollection($parameters),
            null,
        );
    }
}
