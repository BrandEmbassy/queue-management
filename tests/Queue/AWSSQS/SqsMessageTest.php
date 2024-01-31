<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use function str_repeat;

/**
 * @final
 */
class SqsMessageTest extends TestCase
{
    /**
     * @param array<string, array<string, string>> $messageAttributes
     *
     * @dataProvider messageProvider
     */
    public function testIsTooBig(bool $expectedIsTooBig, string $messageBody, array $messageAttributes): void
    {
        Assert::assertSame($expectedIsTooBig, SqsMessage::isTooBig($messageBody, $messageAttributes));
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
