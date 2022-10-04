<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\Sqs\SqsClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use function count;
use function usleep;

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
    private const CREATE_CLIENT_ATTEMPT_DELAYS_IN_MILLISECONDS = [
        1 => 100,
        2 => 500,
        3 => 1000,
    ];

    private LoggerInterface $logger;


    /**
     * @param mixed[] $connectionConfig
     */
    public function __construct(array $connectionConfig, ?LoggerInterface $logger = null)
    {
        if ($logger === null) {
            $logger = new NullLogger();
        }

        parent::__construct($connectionConfig);
        $this->logger = $logger;
    }


    public function create(): SqsClient
    {
        $missing = $this->getMissingRequiredElements();

        if (count($missing) > 0) {
            throw SqsClientException::createFromMissingParameters($missing);
        }

        try {
            return $this->createClientOrRetry();
        } catch (Throwable $exception) {
            throw SqsClientException::createUnableToConnect($exception);
        }
    }


    private function createClientOrRetry(int $attempt = 1): SqsClient
    {
        try {
            return new SqsClient($this->connectionConfig);
        } catch (Throwable $exception) {
            if (!isset(self::CREATE_CLIENT_ATTEMPT_DELAYS_IN_MILLISECONDS[$attempt])) {
                $this->logger->error('SQS client creation failed. Giving up.', [
                    'attempt' => $attempt,
                ]);

                throw $exception;
            }

            $delayInMilliseconds = self::CREATE_CLIENT_ATTEMPT_DELAYS_IN_MILLISECONDS[$attempt];
            $this->logger->warning('SQS client creation failed. Waiting and retrying...', [
                'attempt' => $attempt,
                'delayInMilliseconds' => $delayInMilliseconds,
                'exception' => $exception,
            ]);

            usleep($delayInMilliseconds * 1000);

            return $this->createClientOrRetry($attempt + 1);
        }
    }
}
