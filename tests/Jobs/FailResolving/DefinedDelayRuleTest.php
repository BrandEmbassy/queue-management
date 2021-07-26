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

final class DefinedDelayRuleTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const JOB_NAME = 'jobName';
    private const JOB_CLASS = 'jobClass';
    private const QUEUE_NAME = 'queueName';
    private const QUEUE_JOB_MAX_ATTEMPTS = 10;
    private const MAXIMUM_DELAY = 300;
    private const EMPTY_DELAY_DEFINITION = [0 => 0];
    private const CONSTANT_DELAY_DEFINITION = [0 => 5];
    private const LINEAR_DELAY_DEFINITION = [3 => 30, 0 => 0];
    private const LINEAR_DELAY_DEFINITION_WITHOUT_CONSTANT = [3 => 30, 0 => 5];


    /**
     * @dataProvider attemptsDataProvider
     *
     * @param int[] $constantDelayDefinition
     * @param int[] $linearDelayDefinition
     *
     * @throw Throwable
     */
    public function testCorrectDelayIsReturned(
        int $expectedDelay,
        int $attempts,
        array $constantDelayDefinition,
        array $linearDelayDefinition
    ): void {
        $job = new SimpleJob(
            Uuid::uuid4()->toString(),
            new DateTimeImmutable(),
            $attempts,
            $this->createQueueJobDefinition(),
            new ArrayCollection()
        );
        $delayRule = new DefinedDelayRule(
            self::MAXIMUM_DELAY,
            $constantDelayDefinition,
            $linearDelayDefinition
        );

        $delay = $delayRule->getDelay($job, new Exception());
        Assert::assertSame($expectedDelay, $delay);
    }


    /**
     * @return array<string, mixed>
     */
    public function attemptsDataProvider(): array
    {
        return [
            '1. attempt' => [
                'expectedDelay' => 5,
                'attempts' => 1,
                'constantDelayDefinition' => self::CONSTANT_DELAY_DEFINITION,
                'linearDelayDefinition' => self::LINEAR_DELAY_DEFINITION,
            ],
            '2. attempt' => [
                'expectedDelay' => 5,
                'attempts' => 2,
                'constantDelayDefinition' => self::CONSTANT_DELAY_DEFINITION,
                'linearDelayDefinition' => self::LINEAR_DELAY_DEFINITION,
            ],
            '3. attempt' => [
                'expectedDelay' => 5,
                'attempts' => 3,
                'constantDelayDefinition' => self::CONSTANT_DELAY_DEFINITION,
                'linearDelayDefinition' => self::LINEAR_DELAY_DEFINITION,
            ],
            '4. attempt' => [
                'expectedDelay' => 35,
                'attempts' => 4,
                'constantDelayDefinition' => self::CONSTANT_DELAY_DEFINITION,
                'linearDelayDefinition' => self::LINEAR_DELAY_DEFINITION,
            ],
            '5. attempt' => [
                'expectedDelay' => 65,
                'attempts' => 5,
                'constantDelayDefinition' => self::CONSTANT_DELAY_DEFINITION,
                'linearDelayDefinition' => self::LINEAR_DELAY_DEFINITION,
            ],
            '10. attempt' => [
                'expectedDelay' => 215,
                'attempts' => 10,
                'constantDelayDefinition' => self::CONSTANT_DELAY_DEFINITION,
                'linearDelayDefinition' => self::LINEAR_DELAY_DEFINITION,
            ],
            '15. attempt' => [
                'expectedDelay' => 275,
                'attempts' => 12,
                'constantDelayDefinition' => self::CONSTANT_DELAY_DEFINITION,
                'linearDelayDefinition' => self::LINEAR_DELAY_DEFINITION,
            ],
            '20. attempt' => [
                'expectedDelay' => 300,
                'attempts' => 20,
                'constantDelayDefinition' => self::CONSTANT_DELAY_DEFINITION,
                'linearDelayDefinition' => self::LINEAR_DELAY_DEFINITION,
            ],
            '1. attempt without linear definition' => [
                'expectedDelay' => 5,
                'attempts' => 1,
                'constantDelayDefinition' => self::CONSTANT_DELAY_DEFINITION,
                'linearDelayDefinition' => self::EMPTY_DELAY_DEFINITION,
            ],
            '2. attempt without linear definition' => [
                'expectedDelay' => 5,
                'attempts' => 2,
                'constantDelayDefinition' => self::CONSTANT_DELAY_DEFINITION,
                'linearDelayDefinition' => self::EMPTY_DELAY_DEFINITION,
            ],
            '3. attempt without linear definition' => [
                'expectedDelay' => 5,
                'attempts' => 3,
                'constantDelayDefinition' => self::CONSTANT_DELAY_DEFINITION,
                'linearDelayDefinition' => self::EMPTY_DELAY_DEFINITION,
            ],
            '4. attempt without linear definition' => [
                'expectedDelay' => 5,
                'attempts' => 4,
                'constantDelayDefinition' => self::CONSTANT_DELAY_DEFINITION,
                'linearDelayDefinition' => self::EMPTY_DELAY_DEFINITION,
            ],
            '5. attempt without linear definition' => [
                'expectedDelay' => 5,
                'attempts' => 5,
                'constantDelayDefinition' => self::CONSTANT_DELAY_DEFINITION,
                'linearDelayDefinition' => self::EMPTY_DELAY_DEFINITION,
            ],
            '1. attempt without constant definition' => [
                'expectedDelay' => 5,
                'attempts' => 1,
                'constantDelayDefinition' => self::EMPTY_DELAY_DEFINITION,
                'linearDelayDefinition' => self::LINEAR_DELAY_DEFINITION_WITHOUT_CONSTANT,
            ],
            '2. attempt without constant definition' => [
                'expectedDelay' => 10,
                'attempts' => 2,
                'constantDelayDefinition' => self::EMPTY_DELAY_DEFINITION,
                'linearDelayDefinition' => self::LINEAR_DELAY_DEFINITION_WITHOUT_CONSTANT,
            ],
            '3. attempt without constant definition' => [
                'expectedDelay' => 15,
                'attempts' => 3,
                'constantDelayDefinition' => self::EMPTY_DELAY_DEFINITION,
                'linearDelayDefinition' => self::LINEAR_DELAY_DEFINITION_WITHOUT_CONSTANT,
            ],
            '4. attempt without constant definition' => [
                'expectedDelay' => 50,
                'attempts' => 4,
                'constantDelayDefinition' => self::EMPTY_DELAY_DEFINITION,
                'linearDelayDefinition' => self::LINEAR_DELAY_DEFINITION_WITHOUT_CONSTANT,
            ],
            '5. attempt without constant definition' => [
                'expectedDelay' => 85,
                'attempts' => 5,
                'constantDelayDefinition' => self::EMPTY_DELAY_DEFINITION,
                'linearDelayDefinition' => self::LINEAR_DELAY_DEFINITION_WITHOUT_CONSTANT,
            ],
        ];
    }


    /**
     * @dataProvider badDataProvider
     *
     * @param class-string<Throwable> $expectedException
     * @param int[] $constantDelayDefinition
     * @param int[] $linearDelayDefinition
     */
    public function testExceptionIsThrownWithBadDelayDefinition(
        string $expectedException,
        string $expectedExceptionMessage,
        array $constantDelayDefinition,
        array $linearDelayDefinition
    ): void {
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);

        new DefinedDelayRule(
            self::MAXIMUM_DELAY,
            $constantDelayDefinition,
            $linearDelayDefinition
        );
    }


    /**
     * @return array<string, mixed>
     */
    public function badDataProvider(): array
    {
        return [
            'Constant - Missing definition for 0 attempts' => [
                'expectedException' => DelayRuleException::class,
                'expectedExceptionMessage' => 'Missing definition for 0 attempts',
                'constantDelayDefinition' => [1 => 5],
                'linearDelayDefinition' => self::EMPTY_DELAY_DEFINITION,
            ],
            'Linear - Missing definition for 0 attempts' => [
                'expectedException' => DelayRuleException::class,
                'expectedExceptionMessage' => 'Missing definition for 0 attempts',
                'constantDelayDefinition' => self::EMPTY_DELAY_DEFINITION,
                'linearDelayDefinition' => [1 => 5],
            ],
            'Constant - Incorrect definition order' => [
                'expectedException' => DelayRuleException::class,
                'expectedExceptionMessage' => 'Delays definition keys must be sorted descending',
                'constantDelayDefinition' => [0 => 5, 2 => 5],
                'linearDelayDefinition' => self::EMPTY_DELAY_DEFINITION,
            ],
            'Linear - Incorrect definition order' => [
                'expectedException' => DelayRuleException::class,
                'expectedExceptionMessage' => 'Delays definition keys must be sorted descending',
                'constantDelayDefinition' => self::EMPTY_DELAY_DEFINITION,
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
                self::CONSTANT_DELAY_DEFINITION,
                self::LINEAR_DELAY_DEFINITION
            ),
            Mockery::spy(JobProcessorInterface::class)
        );
    }
}
