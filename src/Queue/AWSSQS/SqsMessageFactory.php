<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\Result;

class SqsMessageFactory {

    /**
     * @return SqsMessage[]
     */
    public static function fromAwsResult(Result $awsResult): array {

        /**
         * @var SqsMessage[] 
         */
        $sqsMessages = []; 

        foreach ($awsResult->get('Messages') as $message) {
            array_push($stack, new SqsMessage($message));
        }
        
        return $sqsMessages;
    }
}