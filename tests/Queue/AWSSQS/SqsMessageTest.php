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
    public function testIsTooBig(): void
    {
        $message = 'very small message';
        $messageThatFitsSoSo = str_pad('very small message', SqsMessage::MAX_SQS_SIZE_KB * 1024);
        $messageThatAlreadyDoesNotFit = str_pad('very small message', SqsMessage::MAX_SQS_SIZE_KB * 1024 + 1);
        Assert::assertFalse(SqsMessage::isTooBig($message));
        Assert::assertFalse(SqsMessage::isTooBig($messageThatFitsSoSo));
        Assert::assertTrue(SqsMessage::isTooBig($messageThatAlreadyDoesNotFit));
    }
}
