<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Aws\Sqs\SqsClient;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Logging\LoggerHelper;
use BE\QueueManagement\Queue\QueueManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function count;

/**
 * @final
 */
class SqsQueueManager implements QueueManagerInterface
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

    private SqsClientFactoryInterface $sqsClientFactory;

    private S3ClientFactoryInterface $s3ClientFactory;

    private SqsClient $sqsClient;

    /**
     * TODO: make private, this if temp for phpcs
     */
    public S3Client $s3Client;

    private LoggerInterface $logger;

    /**
     * -1 = no limit
     */
    private int $consumeLoopIterationsCount;

    /**
     * TODO: make private, this if temp for phpcs
     */
    public ?string $s3bucket;


    public function __construct(SqsClientFactoryInterface $sqsClientFactory, S3ClientFactoryInterface $s3ClientFactory, LoggerInterface $logger, int $consumeLoopIterationsCount = -1, ?string $s3bucket = null)
    {
        $this->sqsClientFactory = $sqsClientFactory;
        $this->sqsClient = $this->sqsClientFactory->create();
        $this->s3ClientFactory = $s3ClientFactory;
        $this->s3Client = $this->s3ClientFactory->create();
        $this->logger = $logger;
        $this->consumeLoopIterationsCount = $consumeLoopIterationsCount;
        $this->s3bucket = $s3bucket;
    }


    public function getConsumeLoopIterationsCount(): int
    {
        return $this->consumeLoopIterationsCount;
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

        $loopIterationsCounter = 0;
        $consumeLoopIterationsCount = $this->getConsumeLoopIterationsCount();

        while (($loopIterationsCounter < $consumeLoopIterationsCount) || $consumeLoopIterationsCount === -1) {
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
                if ($this->consumeLoopIterationsCount !== -1) {
                    $loopIterationsCounter++;
                }
            } catch (AwsException $exception) {
                $this->logger->warning(
                    'AwsException: ' . $exception->getMessage(),
                    ['exception' => $exception],
                );

                $this->reconnect($exception, $queueName);
            }
        }
    }


    public function push(JobInterface $job): void
    {
        $queueName = $job->getJobDefinition()->getQueueName();
        $this->publishMessage($job->toJson(), $queueName);
        LoggerHelper::logJobPushedIntoQueue($job, $queueName, $this->logger);
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
            SqsMessageFields::DELAYSECONDS => $delaySeconds,
            SqsMessageFields::MESSAGEATTRIBUTES => [
                SqsMessageFields::QUEUEURL => [
                    'DataType' => 'String',
                    // queueName might be handy here if we want to consume
                    // from multiple queues in parallel via promises.
                    // Then we need queue in message directly so that we can delete it.
                    'StringValue' => $queueName,
                ],
            ],
            SqsMessageFields::MESSAGEBODY => $message,
            SqsMessageFields::QUEUEURL => $queueName,
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
        $this->s3Client = $this->s3ClientFactory->create();

        $this->logger->warning(
            'Reconnecting: ' . $exception->getMessage(),
            [
                'queueName' => $queueName,
                'exception' => $exception->getTraceAsString(),
            ],
        );
    }


    public function checkConnection(): bool
    {
        // No checkConn method in SqsClient. For now just providing fake response
        // in the future we might want to check somehow whether we still have connectivity.
        return true;
    }
}
