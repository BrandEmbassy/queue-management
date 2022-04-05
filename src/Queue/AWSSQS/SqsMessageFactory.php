<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use function array_push;
use function assert;

/**
 * @final
 */
class SqsMessageFactory
{
    /**
     * @param array<mixed> $awsResultMessages
     *
     * @return SqsMessage[]
     */
    public static function fromAwsResultMessages(array $awsResultMessages, string $queueUrl): array
    {
        /**
         * @var SqsMessage[]
         */
        $sqsMessages = [];

        assert($queueUrl !== '');

        foreach ($awsResultMessages as $message) {
            array_push($sqsMessages, new SqsMessage($message, $queueUrl));
        }

        return $sqsMessages;
    }
}
