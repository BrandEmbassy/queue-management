<?php declare(strict_types = 1);

namespace Queue\AWSSQS;

use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttribute;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttributeDataType;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttributeFactory;
use Iterator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @final
 */
class SqsMessageAttributeFactoryTest extends TestCase
{
    /**
     * @param array{DataType: 'String'|'Number', StringValue: string}|array{DataType: 'Binary', BinaryValue: string} $messageAttributeValue
     */
    #[DataProvider('createFromArrayDataProvider')]
    public function testCreateFromArray(
        string $messageAttributeName,
        array $messageAttributeValue,
        SqsMessageAttribute $expectedSqsMessageAttribute
    ): void {
        $sqsMessageAttributeFactory = new SqsMessageAttributeFactory();

        $sqsMessageAttribute = $sqsMessageAttributeFactory->createFromArray($messageAttributeName, $messageAttributeValue);

        Assert::assertEquals($expectedSqsMessageAttribute, $sqsMessageAttribute);
    }


    /**
     * @return Iterator<string,array{
     *     messageAttributeName: string,
     *     messageAttributeValue: array{DataType: 'String'|'Number', StringValue: string}|array{DataType: 'Binary', BinaryValue: string},
     *     expectedSqsMessageAttribute: SqsMessageAttribute,
     * }>
     */
    public static function createFromArrayDataProvider(): Iterator
    {
        yield 'string attribute' => [
            'messageAttributeName' => 'exampleString',
            'messageAttributeValue' => [
                'DataType' => 'String',
                'StringValue' => 'Hello, World!',
            ],
            'expectedSqsMessageAttribute' => new SqsMessageAttribute(
                'exampleString',
                'Hello, World!',
                SqsMessageAttributeDataType::STRING,
            ),
        ];

        yield 'integer attribute' => [
            'messageAttributeName' => 'exampleNumberInt',
            'messageAttributeValue' => [
                'DataType' => 'Number',
                'StringValue' => '123',
            ],
            'expectedSqsMessageAttribute' => new SqsMessageAttribute(
                'exampleNumberInt',
                '123',
                SqsMessageAttributeDataType::NUMBER,
            ),
        ];

        yield 'float attribute' => [
            'messageAttributeName' => 'exampleNumberFloat',
            'messageAttributeValue' => [
                'DataType' => 'Number',
                'StringValue' => '123.45',
            ],
            'expectedSqsMessageAttribute' => new SqsMessageAttribute(
                'exampleNumberFloat',
                '123.45',
                SqsMessageAttributeDataType::NUMBER,
            ),
        ];

        yield 'binary attribute' => [
            'messageAttributeName' => 'exampleBinary',
            'messageAttributeValue' => [
                'DataType' => 'Binary',
                'BinaryValue' => 'abc',
            ],
            'expectedSqsMessageAttribute' => new SqsMessageAttribute(
                'exampleBinary',
                'abc',
                SqsMessageAttributeDataType::BINARY,
            ),
        ];
    }
}
