<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use function str_pad;

/**
 * @final
 */
class SqsMessageTest extends TestCase
{
    /**
     * @dataProvider messageProvider
     */
    public function testIsTooBig(bool $expectedIsTooBig, string $message): void
    {
        Assert::assertSame($expectedIsTooBig, SqsMessage::isTooBig($message));
    }


    /**
     * @return array<array<string, mixed>>
     */
    public function messageProvider(): array
    {
        return [
            [
                'expectedIsTooBig' => false,
                'message' => 'very small message',
            ],
            [
                'expectedIsTooBig' => false,
                'message' => str_pad('very small message', SqsMessage::MAX_SQS_SIZE_KB * 1024),
            ],
            [
                'expectedIsTooBig' => true,
                'message' => str_pad('very small message', SqsMessage::MAX_SQS_SIZE_KB * 1024 + 1),
            ],
        ];
    }
}
