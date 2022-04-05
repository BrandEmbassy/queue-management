<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\Sqs\SqsClient;
use Throwable;
use function array_key_exists;
use function count;

/**
 * Defines SQS client factory.
 *
 * Contains technnical validation & creation logic of SqsClient
 *
 * See AWS config guide to understand in detail how SqsClient can be initiated:
 * https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_configuration.html
 *
 * @final
 */
class SqsClientFactory implements SqsClientFactoryInterface
{
    public const VERSION = 'version';
    public const REGION = 'region';

    /**
     * For valid options see:
     *  https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.Sqs.SqsClient.html#___construct
     *
     * @var mixed[]
     */
    private array $connectionConfig;


    /**
     * @param mixed[] $connectionConfig
     */
    public function __construct(array $connectionConfig)
    {
        $this->connectionConfig = $connectionConfig;
    }


    public function create(): SqsClient
    {
        $this->checkConfig($this->connectionConfig);

        try {
            return new SqsClient($this->connectionConfig);
        } catch (Throwable $exception) {
            throw SqsClientException::createUnableToConnect($exception);
        }
    }


    /**
     * @param string[]|int[] $connectionConfig
     */
    private function checkConfig(array $connectionConfig): void
    {
        $requiredKeys = [
            self::VERSION,
            self::REGION,
        ];

        $missing = [];

        /** @var string $requiredKey */
        foreach ($requiredKeys as $requiredKey) {
            if (array_key_exists($requiredKey, $connectionConfig)) {
                continue;
            }

            $missing[] = $requiredKey;
        }

        if (count($missing) > 0) {
            throw SqsClientException::createFromMissingParameters($missing);
        }
    }
}
