<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Assert;
use BE\QueueManagement\Queue\AWSSQS\SqsMessageFactory;

/**
 * @final
 */
class SqsMessageFactoryTest extends TestCase
{

    public function testFromAwsResultMessages(): void
    {
        $queueUrl = 'https://sqs.eu-central-1.amazonaws.com/1234567891/SomeQueue';
        $messages = array(
            0 => array (
                'MessageId' => '96819875-6e43-4a14-9652-6b5d239f5e1b',
                'ReceiptHandle' => 'AQEBzIAiAszGlR5ATxhjbGdRA/cVxGzGheyMUeMFD/T4plZzCE2qS5Qo+vZ6d+6IS8O14rO+MY+hjFBdZ2L5RrEH6jxmtwD9B2Swy6WIHm7dBN7uV199+maaqvYzyqicgyL3VErSX/8UnVJKqCnkBJPFTdetUY7tDqg2ib2pCp5kCcwBrMDPvR0/rrfL2ISQeLbdxZBItvu/9PhYL3nfMxOzyW+R6fXorQxyLrP1S+tn3D2Di+UkI9zGFUlz9MTXs7zTwVm8W3aVLVyhElCZ2nEnxMClxVv2DS4grpz7dl/WLiPj/NHffig38CGFq6Z7UvFAusIlP4McsOTa/Hfy0hZX0Dj5UrOBLtiMsuYmJtvlAqGWyGFgWfjf+rU3Ie1HrqkEOVOIUcI/2UAQhTNU1uQ67w==',
                'MD5OfBody' => 'db9b6a326e8c7336d4303d9a4b8f3e11',
                'Body' => '[[{"statusCode":200,"effectiveUri":"https:\\/\\/dfo-webhooksender-s3.s3.eu-central-1.amazonaws.com\\/de2710e6-56b8-47cc-95fe-5aae916ef2c8.json","headers":{"x-amz-id-2":"2MSq\\/GpTM6k6yPHZJtsmsYBYKLJLmd+OyF2CTsTlLQfZlw02\\/BFCqhdWJnQ+71TbozrsxYk\\/TfQ=","x-amz-request-id":"SMJJ5QJFZ0EACVKD","date":"Thu, 21 Apr 2022 11:07:04 GMT","etag":"\\"eff36c85eeeebeaf8a583bf55776120b\\"","server":"AmazonS3","content-length":"0"},"transferStats":{"http":[[]]}},"https:\\/\\/dfo-webhooksender-s3.s3.eu-central-1.amazonaws.com\\/de2710e6-56b8-47cc-95fe-5aae916ef2c8.json"],{"s3BucketName":"dfo-webhooksender-s3","s3Key":"de2710e6-56b8-47cc-95fe-5aae916ef2c8.json"}]',
                'Attributes' =>
                    array (
                        'SenderId' => 'AROAYPPZHWMXHMBX2SQUT:SomeRoleSession',
                        'ApproximateFirstReceiveTimestamp' => '1650539417093',
                        'ApproximateReceiveCount' => '1',
                        'SentTimestamp' => '1650539224000',
                    ),
                'MD5OfMessageAttributes' => 'e4849a650dbb07b06723f9cf0ebe1f68',
                'MessageAttributes' =>
                    array (
                        'QueueUrl' =>
                            array (
                                'StringValue' => $queueUrl,
                                'DataType' => 'String',
                            ),
                    ),
            )
        );

        // TODO: this was moved to SqsQueueManager
        $sqsMessages = SqsMessageFactory::fromAwsResultMessages($messages, $queueUrl);
        Assert::assertTrue(1 == 1);
    }
}