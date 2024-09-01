<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use BE\QueueManagement\Jobs\SimpleJob;
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
        string $messageAttributeName,
        ?string $messageAttributeValue,
        SqsMessageAttributeDataType $messageAttributeDataType,
        string|int|float|null $expectedResult
    ): void {
        if ($messageAttributeValue !== null) {
            $this->job->setMessageAttribute(
                $messageAttributeName,
                $messageAttributeValue,
                $messageAttributeDataType,
            );
        }

        $result = $this->job->getMessageAttribute($messageAttributeName, $messageAttributeDataType);
        Assert::assertSame($expectedResult, $result);
    }


    /**
     * @return Iterator<string,array{
     *     messageAttributeName: string,
     *     messageAttributeValue: ?string,
     *     messageAttributeDataType: SqsMessageAttributeDataType,
     *     expectedResult: string|int|float|null,
     * }>
     */
    public static function getSetMessageAttributeDataProvider(): Iterator
    {
        yield 'string attribute' => [
            'messageAttributeName' => 'exampleString',
            'messageAttributeValue' => 'Hello, World!',
            'messageAttributeDataType' => SqsMessageAttributeDataType::STRING,
            'expectedResult' => 'Hello, World!',
        ];

        yield 'integer attribute' => [
            'messageAttributeName' => 'exampleNumberInt',
            'messageAttributeValue' => '123',
            'messageAttributeDataType' => SqsMessageAttributeDataType::NUMBER,
            'expectedResult' => 123,
        ];

        yield 'float attribute' => [
            'messageAttributeName' => 'exampleNumberFloat',
            'messageAttributeValue' => '123.45',
            'messageAttributeDataType' => SqsMessageAttributeDataType::NUMBER,
            'expectedResult' => 123.45,
        ];

        yield 'attribute not set' => [
            'messageAttributeName' => 'exampleNumberFloat',
            'messageAttributeValue' => null,
            'messageAttributeDataType' => SqsMessageAttributeDataType::STRING,
            'expectedResult' => null,
        ];
    }
}
