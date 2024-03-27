<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\FailResolving;

use BE\QueueManagement\Jobs\Execution\JobProcessorInterface;
use BE\QueueManagement\Jobs\FailResolving\DelayRules\DefinedDelayRule;
use BE\QueueManagement\Jobs\FailResolving\DelayRules\DelayRuleException;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinition;
use BE\QueueManagement\Jobs\Loading\JobLoaderInterface;
use BE\QueueManagement\Jobs\SimpleJob;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * @final
 */
class DefinedDelayRuleTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const JOB_NAME = 'jobName';

    private const JOB_CLASS = 'jobClass';

    private const QUEUE_NAME = 'queueName';

    private const QUEUE_JOB_MAX_ATTEMPTS = 10;

    private const MAXIMUM_DELAY = 300;

    private const LINEAR_DELAY_DEFINITION = [4 => 30, 0 => 5];


    /**
     * @throw Throwable
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('attemptsDataProvider')]
    public function testCorrectDelayIsReturned(
        int $expectedDelay,
        int $attempts
    ): void {
        $job = new SimpleJob(
            Uuid::uuid4()->toString(),
            new DateTimeImmutable(),
            $attempts,
            $this->createQueueJobDefinition(),
            new ArrayCollection(),
            null,
        );
        $delayRule = new DefinedDelayRule(
            self::MAXIMUM_DELAY,
            self::LINEAR_DELAY_DEFINITION,
        );

        $delay = $delayRule->getDelay($job, new Exception());
        Assert::assertSame($expectedDelay, $delay);
    }


    /**
     * @return array<string, mixed>
     */
    public static function attemptsDataProvider(): array
    {
        return [
            '1. attempt' => [
                'expectedDelay' => 5,
                'attempts' => 1,
            ],
            '2. attempt' => [
                'expectedDelay' => 10,
                'attempts' => 2,
            ],
            '3. attempt' => [
                'expectedDelay' => 15,
                'attempts' => 3,
            ],
            '4. attempt' => [
                'expectedDelay' => 120,
                'attempts' => 4,
            ],
            '5. attempt' => [
                'expectedDelay' => 150,
                'attempts' => 5,
            ],
            '10. attempt' => [
                'expectedDelay' => 300,
                'attempts' => 10,
            ],
            '15. attempt' => [
                'expectedDelay' => 300,
                'attempts' => 12,
            ],
        ];
    }


    /**
     *
     * @param class-string<Throwable> $expectedException
     * @param int[] $linearDelayDefinition
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('badDataProvider')]
    public function testExceptionIsThrownWithBadDelayDefinition(
        string $expectedException,
        string $expectedExceptionMessage,
        array $linearDelayDefinition
    ): void {
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);

        new DefinedDelayRule(
            self::MAXIMUM_DELAY,
            $linearDelayDefinition,
        );
    }


    /**
     * @return array<string, mixed>
     */
    public static function badDataProvider(): array
    {
        return [
            'Missing definition for 0 attempts' => [
                'expectedException' => DelayRuleException::class,
                'expectedExceptionMessage' => 'Missing definition for 0 attempts',
                'linearDelayDefinition' => [1 => 5],
            ],
            'Incorrect definition order' => [
                'expectedException' => DelayRuleException::class,
                'expectedExceptionMessage' => 'Delays definition keys must be sorted descending',
                'linearDelayDefinition' => [0 => 5, 2 => 5],
            ],
        ];
    }


    private function createQueueJobDefinition(): JobDefinition
    {
        return new JobDefinition(
            self::JOB_NAME,
            self::JOB_CLASS,
            self::QUEUE_NAME,
            self::QUEUE_JOB_MAX_ATTEMPTS,
            Mockery::spy(JobLoaderInterface::class),
            new DefinedDelayRule(
                self::MAXIMUM_DELAY,
                self::LINEAR_DELAY_DEFINITION,
            ),
            Mockery::spy(JobProcessorInterface::class),
        );
    }
}
