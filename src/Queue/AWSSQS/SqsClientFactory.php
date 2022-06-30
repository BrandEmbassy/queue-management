<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\Sqs\SqsClient;
use Throwable;
use function count;

/**
 * Defines SQS client factory.
 *
 * Contains creation logic of SqsClient
 *
 * See AWS config guide to understand in detail how SqsClient can be initiated:
 * https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_configuration.html
 *
 * @final
 */
class SqsClientFactory extends AwsClientFactory implements SqsClientFactoryInterface
{
    /**
     * @param mixed[] $connectionConfig
     */
    public function __construct(array $connectionConfig)
    {
        parent::__construct($connectionConfig);
    }


    public function create(): SqsClient
    {
        $missing = $this->getMissingRequiredElements();

        if (count($missing) > 0) {
            throw SqsClientException::createFromMissingParameters($missing);
        }

        try {
            return new SqsClient($this->connectionConfig);
        } catch (Throwable $exception) {
            throw SqsClientException::createUnableToConnect($exception);
        }
    }
}
