<?php declare(strict_types = 1);

namespace Queue\AWSSQS;

use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttribute;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttributeDataType;
use Iterator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @final
 */
class SqsMessageAttributeTest extends TestCase
{
    /**
     * @param array{DataType: 'String'|'Number', StringValue: string}|array{DataType: 'Binary', BinaryValue: string} $expectedArray
     */
    #[DataProvider('sqsMessageAttributeDataProvider')]
    public function testSqsMessageAttribute(
        SqsMessageAttribute $sqsMessageAttribute,
        string|int|float $expectedSqsMessageAttributeValue,
        array $expectedArray,
        int $expectedSizeInBytes,
    ): void {
        Assert::assertSame($expectedSqsMessageAttributeValue, $sqsMessageAttribute->getValue());
        Assert::assertSame($expectedArray, $sqsMessageAttribute->toArray());
        Assert::assertSame($expectedSizeInBytes, $sqsMessageAttribute->getSizeInBytes());
    }


    /**
     * @return Iterator<string,array{
     *     sqsMessageAttribute: SqsMessageAttribute,
     *     expectedSqsMessageAttributeValue: string|int|float,
     *     expectedArray: array{DataType: 'String'|'Number', StringValue: string}|array{DataType: 'Binary', BinaryValue: string},
     *     expectedSizeInBytes: int,
     * }>
     */
    public static function sqsMessageAttributeDataProvider(): Iterator
    {
        yield 'string attribute' => [
            'sqsMessageAttribute' => new SqsMessageAttribute(
                'exampleString',
                'Hello, World!',
                SqsMessageAttributeDataType::STRING,
            ),
            'expectedSqsMessageAttributeValue' => 'Hello, World!',
            'expectedArray' => [
                'DataType' => 'String',
                'StringValue' => 'Hello, World!',
            ],
            'expectedSizeInBytes' => 19,
        ];

        yield 'integer attribute' => [
            'sqsMessageAttribute' => new SqsMessageAttribute(
                'exampleInteger',
                '123',
                SqsMessageAttributeDataType::NUMBER,
            ),
            'expectedSqsMessageAttributeValue' => 123,
            'expectedArray' => [
                'DataType' => 'Number',
                'StringValue' => '123',
            ],
            'expectedSizeInBytes' => 9,
        ];

        yield 'float attribute' => [
            'sqsMessageAttribute' => new SqsMessageAttribute(
                'exampleFloat',
                '123.45',
                SqsMessageAttributeDataType::NUMBER,
            ),
            'expectedSqsMessageAttributeValue' => 123.45,
            'expectedArray' => [
                'DataType' => 'Number',
                'StringValue' => '123.45',
            ],
            'expectedSizeInBytes' => 12,
        ];

        yield 'binary attribute' => [
            'sqsMessageAttribute' => new SqsMessageAttribute(
                'exampleBinary',
                'abc',
                SqsMessageAttributeDataType::BINARY,
            ),
            'expectedSqsMessageAttributeValue' => 'abc',
            'expectedArray' => [
                'DataType' => 'Binary',
                'BinaryValue' => 'abc',
            ],
            'expectedSizeInBytes' => 9,
        ];
    }
}
