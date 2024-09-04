<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use BE\QueueManagement\Jobs\SimpleJob;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttribute;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttributeDataType;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Iterator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SimpleJobTest extends TestCase
{
    private SimpleJob $job;


    protected function setUp(): void
    {
        $uuid = '123-456';
        $createdAt = new DateTimeImmutable();
        $attempts = 1;
        $jobDefinition = $this->createMock(JobDefinitionInterface::class);
        $parameters = new ArrayCollection();
        $executionPlannedAt = new DateTimeImmutable();

        $this->job = new SimpleJob(
            $uuid,
            $createdAt,
            $attempts,
            $jobDefinition,
            $parameters,
            $executionPlannedAt,
        );
    }


    #[DataProvider('getSetMessageAttributeDataProvider')]
    public function testSetAndGetMessageAttribute(
        SqsMessageAttribute $sqsMessageAttribute,
        string|int|float|null $expectedResult
    ): void {
        $this->job->setMessageAttribute($sqsMessageAttribute);

        $result = $this->job->getMessageAttribute($sqsMessageAttribute->getName());
        Assert::assertSame($expectedResult, $result?->getValue());
    }


    /**
     * @return Iterator<string,array{
     *     sqsMessageAttribute: SqsMessageAttribute,
     *     expectedResult: string|int|float,
     * }>
     */
    public static function getSetMessageAttributeDataProvider(): Iterator
    {
        yield 'string attribute' => [
            'sqsMessageAttribute' => new SqsMessageAttribute(
                'exampleString',
                'Hello, World!',
                SqsMessageAttributeDataType::STRING,
            ),
            'expectedResult' => 'Hello, World!',
        ];

        yield 'integer attribute' => [
            'sqsMessageAttribute' => new SqsMessageAttribute(
                'exampleNumberInt',
                '123',
                SqsMessageAttributeDataType::NUMBER,
            ),
            'expectedResult' => 123,
        ];

        yield 'float attribute' => [
            'sqsMessageAttribute' => new SqsMessageAttribute(
                'exampleNumberFloat',
                '123.45',
                SqsMessageAttributeDataType::NUMBER,
            ),
            'expectedResult' => 123.45,
        ];
    }
}
