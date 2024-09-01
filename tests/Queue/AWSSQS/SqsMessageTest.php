<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttributeDataType;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttributeFields;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageFields;
use Iterator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function str_repeat;
use function strlen;

/**
 * @final
 */
class SqsMessageTest extends TestCase
{
    /**
     * @param array<string, array<string, string>> $messageAttributes
     */
    #[DataProvider('messageProvider')]
    public function testIsTooBig(bool $expectedIsTooBig, string $messageBody, array $messageAttributes): void
    {
        Assert::assertSame($expectedIsTooBig, SqsMessage::isTooBig($messageBody, $messageAttributes));
    }


    #[DataProvider('getMessageAttributeDataProvider')]
    public function testGetMessageAttribute(
        string $messageAttributeName,
        ?string $messageAttributeValue,
        SqsMessageAttributeDataType $messageAttributeDataType,
        string|int|float|null $expectedResult
    ): void {
        $message = [
            SqsMessageFields::MESSAGE_ATTRIBUTES => [
                $messageAttributeName => [
                    $messageAttributeDataType === SqsMessageAttributeDataType::BINARY ?
                        SqsMessageAttributeFields::BINARY_VALUE->value : SqsMessageAttributeFields::STRING_VALUE->value => $messageAttributeValue,
                ],
            ],
        ];

        $sqsMessage = new SqsMessage($message, 'https://sqs.eu-central-1.amazonaws.com/1234567891/SomeQueue');

        $result = $sqsMessage->getMessageAttribute($messageAttributeName, $messageAttributeDataType);

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
    public static function getMessageAttributeDataProvider(): Iterator
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
            'messageAttributeName' => 'notSet',
            'messageAttributeValue' => null,
            'messageAttributeDataType' => SqsMessageAttributeDataType::STRING,
            'expectedResult' => null,
        ];
    }


    /**
     * @return array<array<string, mixed>>
     */
    public static function messageProvider(): array
    {
        $messageAttributes = [
            'QueueUrl' => [
                'DataType' => 'String',
                'StringValue' => 'https://sqs.eu-central-1.amazonaws.com/1234567891/SomeQueue',
            ],
        ];

        $messageBodySizeLimit = SqsMessage::MAX_SQS_SIZE_KB * 1024
            - strlen('QueueUrl')
            - strlen('String')
            - strlen('https://sqs.eu-central-1.amazonaws.com/1234567891/SomeQueue');

        return [
            'small message' => [
                'expectedIsTooBig' => false,
                'messageBody' => 'message',
                'messageAttributes' => $messageAttributes,
            ],
            'message just within the limit' => [
                'expectedIsTooBig' => false,
                'messageBody' => str_repeat('A', $messageBodySizeLimit),
                'messageAttributes' => $messageAttributes,
            ],
            'too big message' => [
                'expectedIsTooBig' => true,
                'messageBody' => str_repeat('A', $messageBodySizeLimit + 1),
                'messageAttributes' => $messageAttributes,
            ],
        ];
    }
}
