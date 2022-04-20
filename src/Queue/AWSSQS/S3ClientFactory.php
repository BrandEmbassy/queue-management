<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\S3\S3Client;
use Throwable;
use function count;

/**
 * Defines S3 client factory.
 *
 * Contains creation logic of S3Client
 *
 * See AWS config guide to understand in detail how SqsClient can be initiated:
 * https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_configuration.html
 *
 * @final
 */
class S3ClientFactory extends AwsClientFactory implements S3ClientFactoryInterface
{
    /**
     * @param array<mixed> $connectionConfig
     */
    public function __construct(array $connectionConfig)
    {
        parent::__construct($connectionConfig);
    }


    public function create(): S3Client
    {
        $missing = $this->getMissingRequiredElements();

        if (count($missing) > 0) {
            throw S3ClientException::createFromMissingParameters($missing);
        }

        try {
            return new S3Client($this->connectionConfig);
        } catch (Throwable $exception) {
            throw S3ClientException::createUnableToConnect($exception);
        }
    }
}
