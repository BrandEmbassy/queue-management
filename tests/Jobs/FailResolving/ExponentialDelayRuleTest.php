<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\FailResolving;

use BE\QueueManagement\Jobs\Execution\JobProcessorInterface;
use BE\QueueManagement\Jobs\FailResolving\DelayRules\ExponentialDelayRule;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinition;
use BE\QueueManagement\Jobs\Loading\JobLoaderInterface;
use BE\QueueManagement\Jobs\SimpleJob;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use Generator;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * @final
 */
class ExponentialDelayRuleTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const INITIAL_DELAY_IN_MILLISECONDS = 3;

    private const MAXIMUM_DELAY_IN_MILLISECONDS = 9000;


    /**
     * @throw Throwable
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('attemptNumberDataProvider')]
    public function testCorrectDelayIsReturned(
        int $expectedDelayInMilliseconds,
        int $expectedDelayInSeconds,
        int $attemptNumber,
    ): void {
        $job = $this->createSimpleJob($attemptNumber);

        $delayRule = new ExponentialDelayRule(
            self::INITIAL_DELAY_IN_MILLISECONDS,
            self::MAXIMUM_DELAY_IN_MILLISECONDS,
        );

        $actualDelayInMilliseconds = $delayRule->getDelayWithMilliseconds($job, new Exception());
        Assert::assertSame($expectedDelayInMilliseconds, $actualDelayInMilliseconds);

        $actualDelayInSeconds = $delayRule->getDelay($job, new Exception());
        Assert::assertSame($expectedDelayInSeconds, $actualDelayInSeconds);
    }


    public static function attemptNumberDataProvider(): Generator
    {
        yield 'attempt #1' => [
            'expectedDelayInMilliseconds' => 3,
            'expectedDelayInSeconds' => 0,
            'attemptNumber' => 1,
        ];
        yield 'attempt #2' => [
            'expectedDelayInMilliseconds' => 6,
            'expectedDelayInSeconds' => 0,
            'attemptNumber' => 2,
        ];
        yield 'attempt #3' => [
            'expectedDelayInMilliseconds' => 12,
            'expectedDelayInSeconds' => 0,
            'attemptNumber' => 3,
        ];
        yield 'attempt #4' => [
            'expectedDelayInMilliseconds' => 24,
            'expectedDelayInSeconds' => 0,
            'attemptNumber' => 4,
        ];
        yield 'attempt #5' => [
            'expectedDelayInMilliseconds' => 48,
            'expectedDelayInSeconds' => 0,
            'attemptNumber' => 5,
        ];
        yield 'attempt #6' => [
            'expectedDelayInMilliseconds' => 96,
            'expectedDelayInSeconds' => 0,
            'attemptNumber' => 6,
        ];
        yield 'attempt #9' => [
            'expectedDelayInMilliseconds' => 768,
            'expectedDelayInSeconds' => 0,
            'attemptNumber' => 9,
        ];
        yield 'attempt #10' => [
            'expectedDelayInMilliseconds' => 1536,
            'expectedDelayInSeconds' => 1,
            'attemptNumber' => 10,
        ];
        yield 'attempt #11' => [
            'expectedDelayInMilliseconds' => 3072,
            'expectedDelayInSeconds' => 3,
            'attemptNumber' => 11,
        ];
        yield 'attempt #12' => [
            'expectedDelayInMilliseconds' => 6144,
            'expectedDelayInSeconds' => 6,
            'attemptNumber' => 12,
        ];
        yield 'attempt #13' => [
            'expectedDelayInMilliseconds' => 9000,
            'expectedDelayInSeconds' => 9,
            'attemptNumber' => 13,
        ];
        yield 'attempt #100' => [
            'expectedDelayInMilliseconds' => 9000,
            'expectedDelayInSeconds' => 9,
            'attemptNumber' => 100,
        ];
    }


    private function createSimpleJob(int $attempts): SimpleJob
    {
        return new SimpleJob(
            Uuid::uuid4()->toString(),
            new DateTimeImmutable(),
            $attempts,
            $this->createJobDefinition(),
            new ArrayCollection(),
            null,
        );
    }


    private function createJobDefinition(): JobDefinition
    {
        return new JobDefinition(
            'job-name',
            'jobClass',
            'queue-name',
            1,
            Mockery::mock(JobLoaderInterface::class),
            new ExponentialDelayRule(
                self::INITIAL_DELAY_IN_MILLISECONDS,
                self::MAXIMUM_DELAY_IN_MILLISECONDS,
            ),
            Mockery::mock(JobProcessorInterface::class),
        );
    }
}
