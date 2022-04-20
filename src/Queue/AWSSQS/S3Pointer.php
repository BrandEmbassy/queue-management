<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\ResultInterface;
use function array_diff;
use function array_map;
use function count;
use function is_array;
use function json_encode;

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
     * @param ResultInterface<mixed> $s3_result
     */
    public function __construct(string $bucket_name, string $key, ResultInterface $s3_result)
    {
        $this->bucketName = $bucket_name;
        $this->key = $key;
        $this->s3Result = $s3_result;
    }


    public function __toString(): string
    {
        $info_keys = ['@metadata', 'ObjectUrl'];
        $metadata = array_map([$this->s3Result, 'get'], $info_keys);
        $pointer = ['s3BucketName' => $this->bucketName, 's3Key' => $this->key];

        return (string)json_encode([$metadata, $pointer]);
    }


    /**
     * TODO: fix this! see index TBD
     *
     * @param ResultInterface<mixed> $result
     */
    public static function isS3Pointer(ResultInterface $result): bool
    {
      // Check that the second element of the 2 position array has the expected
      // keys (and no more).
        return $result->count() === 2 &&
        is_array($result->get('TBD')) &&
        count(array_diff($result->get('TBD'), ['s3BucketName', 's3Key'])) === 0;
    }
}
