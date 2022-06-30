<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use Aws\Result;
use BE\QueueManagement\Queue\AWSSQS\S3Pointer;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/**
 * @final
 */
class S3PointerTest extends TestCase
{
    public function testToString(): void
    {
        $awsResult = new Result([
            'Expiration' => '',
            'ETag' => '"9d2ee07f934614ed5a5ff3193b306a56"',
            'ServerSideEncryption' => '',
            'VersionId' => '',
            'SSECustomerAlgorithm' => '',
            'SSECustomerKeyMD5' => '',
            'SSEKMSKeyId' => '',
            'SSEKMSEncryptionContext' => '',
            'BucketKeyEnabled' => false,
            'RequestCharged' => '',
            '@metadata' => [
                'statusCode' => 200,
                'effectiveUri' => 'https://some-s3-bucket-name.s3.eu-central-1.amazonaws.com/d8c153df-516f-4541-a532-381c74ac521d.json',
                'headers' =>
                    [
                        'x-amz-id-2' => 'rgH/K38hKZ6H4rh+23i9DKFWO7ngfKinO3Ext8aUoj3ETNCydQstwS8mTVaHeni3jiVbJs2UClo=',
                        'x-amz-request-id' => 'VP57QZFDPAY21RAR',
                        'date' => 'Thu, 21 Apr 2022 10:31:53 GMT',
                        'etag' => '"9d2ee07f934614ed5a5ff3193b306a56"',
                        'server' => 'AmazonS3',
                        'content-length' => '0',
                    ],
                'transferStats' =>
                    [
                        'http' =>
                            [
                                0 =>
                                    [],
                            ],
                    ],
            ],
            'ObjectUrl' => 'https://some-s3-bucket-name.s3.eu-central-1.amazonaws.com/d8c153df-516f-4541-a532-381c74ac521d.json',
        ]);

        $pointer = new S3Pointer('some-s3-bucket-name', 'd8c153df-516f-4541-a532-381c74ac521d.json', $awsResult);
        $pointerStr = (string)$pointer;
        $expectedPointerStr = '[[{"statusCode":200,"effectiveUri":"https:\/\/some-s3-bucket-name.s3.eu-central-1.amazonaws.com\/d8c153df-516f-4541-a532-381c74ac521d.json","headers":{"x-amz-id-2":"rgH\/K38hKZ6H4rh+23i9DKFWO7ngfKinO3Ext8aUoj3ETNCydQstwS8mTVaHeni3jiVbJs2UClo=","x-amz-request-id":"VP57QZFDPAY21RAR","date":"Thu, 21 Apr 2022 10:31:53 GMT","etag":"\"9d2ee07f934614ed5a5ff3193b306a56\"","server":"AmazonS3","content-length":"0"},"transferStats":{"http":[[]]}},null],{"s3BucketName":"some-s3-bucket-name","s3Key":"d8c153df-516f-4541-a532-381c74ac521d.json"}]';
        Assert::assertTrue($pointerStr === $expectedPointerStr);
    }
}
