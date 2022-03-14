<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Queue\Common\Logger;
use BE\QueueManagement\Queue\QueueManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function count;

final class SqsQueueManager implements QueueManagerInterface
{
    /**
     * The maximum number of messages to return.
     * Amazon SQS never returns more messages than this value (however, fewer messages might be returned).
     * Valid values: 1 to 10. Default: 1.
     */
    public const MAX_NUMBER_OF_MESSAGES = 'MaxNumberOfMessages';

    public const WAIT_TIME_SECONDS = 'WaitTimeSeconds';

    public const DELAY_SECONDS = 'DelaySeconds';

    // SQS allows maximum message delay of 15 minutes
    public const MAX_DELAY_SECONDS = 15 * 60;

    public const UNIT_TEST_CONTEXT = '_unit_test_ctx_';

    private SqsClientFactoryInterface $sqsClientFactory;

    private SqsClient $sqsClient;

    private LoggerInterface $logger;


    public function __construct(SqsClientFactoryInterface $sqsClientFactory, LoggerInterface $logger)
    {
        $this->sqsClientFactory = $sqsClientFactory;
        $this->sqsClient = $this->sqsClientFactory->create();
        $this->logger = $logger;
    }


    /**
     * @param mixed[] $parameters
     *
     * @throws SqsClientException
     */
    public function consumeMessages(callable $consumer, string $queueName, array $parameters = []): void
    {
        $maxNumberOfMessages = (int)($parameters[self::MAX_NUMBER_OF_MESSAGES] ?? 10);
        $waitTimeSeconds = (int)($parameters[self::WAIT_TIME_SECONDS] ?? 10);
        $isUnitTest = (bool)($parameters[self::UNIT_TEST_CONTEXT] ?? false);

        while (true) {
            try {
                $result = $this->sqsClient->receiveMessage([
                    'AttributeNames' => ['All'],
                    'MaxNumberOfMessages' => $maxNumberOfMessages,
                    'MessageAttributeNames' => ['All'],
                    'QueueUrl' => $queueName,
                    'WaitTimeSeconds' => $waitTimeSeconds,
                ]);

                $messages = $result->get('Messages');
                if (count($messages) > 0) {
                    $sqsMessages = SqsMessageFactory::fromAwsResultMessages($messages, $queueName);
                    foreach ($sqsMessages as $sqsMessage) {
                        $consumer($sqsMessage);
                    }
                }

                // call only once in unit test!
                if ($isUnitTest) {
                    break;
                }
            } catch (AwsException $exception) {
                $this->logger->warning(
                    'AwsException: ' . $exception->getMessage(),
                    ['exception' => $exception]
                );

                $this->reconnect($exception, $queueName);
            }
        }
    }


    public function push(JobInterface $job): void
    {
        $queueName = $job->getJobDefinition()->getQueueName();
        $this->publishMessage($job->toJson(), $queueName);
        Logger::logJobPushedIntoQueue($job, $queueName, $this->logger);
    }


    public function pushDelayedWithMilliseconds(JobInterface $job, int $delayInMilliseconds): void
    {
        $this->pushDelayed($job, $delayInMilliseconds / 1000);
    }


    public function pushDelayed(JobInterface $job, int $delayInSeconds): void
    {
        $queueName = $job->getJobDefinition()->getQueueName();

        $parameters = [
            self::DELAY_SECONDS => $delayInSeconds,
        ];

        $this->publishMessage($job->toJson(), $queueName, $parameters);
    }


    /**
     * @param mixed[] $properties
     *
     * @throws AwsException
     * @throws SqsClientException
     */
    private function publishMessage(string $message, string $queueName, array $properties = []): void
    {
        $delaySeconds = (int)($properties[self::DELAY_SECONDS] ?? 0);

        if ($delaySeconds < 0 || $delaySeconds > self::MAX_DELAY_SECONDS) {
            throw SqsClientException::createFromInvalidDelaySeconds($delaySeconds);
        }

        $sqsMessage = [
            'DelaySeconds' => $delaySeconds,
            'MessageAttributes' => [
                'QueueUrl' => [
                    'DataType' => 'String',
                    // queueName might be handy here if we want to consume
                    // from multiple queues in parallel via promises.
                    // Then we need queue in message directly so that we can delete it.
                    'StringValue' => $queueName,
                ],
            ],
            'MessageBody' => $message,
            'QueueUrl' => $queueName,
        ];

        try {
            $this->sqsClient->sendMessage($sqsMessage);
        } catch (AwsException $exception) {
            $this->reconnect($exception, $queueName);
            $this->sqsClient->sendMessage($sqsMessage);
        }
    }


    /**
     * @throws SqsClientException
     */
    private function reconnect(Throwable $exception, string $queueName): void
    {
        $this->sqsClient = $this->sqsClientFactory->create();

        $this->logger->warning(
            'Reconnecting: ' . $exception->getMessage(),
            [
                'queueName' => $queueName,
                'exception' => $exception->getTraceAsString(),
            ]
        );
    }


    public function checkConnection(): bool
    {
        // No checkConn method in SqsClient. For now just providing fake response
        // in the future we might want to check somehow whether we still have connectivity.
        return true;
    }
}
