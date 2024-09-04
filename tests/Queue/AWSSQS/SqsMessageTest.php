<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttribute;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageAttributeDataType;
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
     * @param array<string,SqsMessageAttribute> $messageAttributes
     */
    #[DataProvider('messageProvider')]
    public function testIsTooBig(bool $expectedIsTooBig, string $messageBody, array $messageAttributes): void
    {
        Assert::assertSame($expectedIsTooBig, SqsMessage::isTooBig($messageBody, $messageAttributes));
    }


    /**
     * @return array<string, array{
     *     expectedIsTooBig: bool,
     *     messageBody: string,
     *     messageAttributes: array<string,SqsMessageAttribute>,
     * }>
     */
    public static function messageProvider(): array
    {
        $messageAttributes = [
            'QueueUrl' => new SqsMessageAttribute(
                'QueueUrl',
                'https://sqs.eu-central-1.amazonaws.com/1234567891/SomeQueue',
                SqsMessageAttributeDataType::STRING,
            ),
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
