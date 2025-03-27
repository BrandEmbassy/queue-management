<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\ResultInterface;
use function array_map;
use function count;
use function is_array;
use function is_object;
use function json_encode;
use function property_exists;

/**
 * @final
 */
class S3Pointer
{
    /**
     * The name of the bucket.
     */
    protected string $bucketName;

    /**
     * The ID of the S3 document.
     */
    protected string $key;

    /**
     * @var ResultInterface<mixed>
     */
    protected ResultInterface $s3Result;


    /**
     * @param ResultInterface<mixed> $s3Result
     */
    public function __construct(string $bucketName, string $key, ResultInterface $s3Result)
    {
        $this->bucketName = $bucketName;
        $this->key = $key;
        $this->s3Result = $s3Result;
    }


    public function __toString(): string
    {
        $info_keys = ['@metadata', 'ObjectURL'];
        $metadata = array_map(fn(string $key) => $this->s3Result->get($key), $info_keys);
        $pointer = ['s3BucketName' => $this->bucketName, 's3Key' => $this->key];

        return (string)json_encode([$metadata, $pointer]);
    }


    /**
     * @param array<mixed> $messageBody
     */
    public static function isS3Pointer(array $messageBody): bool
    {
        return count($messageBody) === 2 &&
            is_array($messageBody[0]) &&
            is_object($messageBody[1]) &&
            property_exists($messageBody[1], 's3BucketName') &&
            property_exists($messageBody[1], 's3Key');
    }


    /**
     * @param array<mixed> $messageBody
     */
    public static function getBucketNameFromValidS3Pointer(array $messageBody): string
    {
        if (self::isS3Pointer($messageBody)) {
            return $messageBody[1]->s3BucketName;
        }

        return '';
    }


    /**
     * @param array<mixed> $messageBody
     */
    public static function getS3KeyFromValidS3Pointer(array $messageBody): string
    {
        if (self::isS3Pointer($messageBody)) {
            return $messageBody[1]->s3Key;
        }

        return '';
    }
}
