<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Aws\Sqs\SqsClient;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Logging\LoggerContextField;
use BE\QueueManagement\Logging\LoggerHelper;
use BE\QueueManagement\Queue\QueueManagerInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Log\LoggerInterface;
use Throwable;
use function assert;
use function count;
use function is_array;
use function json_decode;
use function sprintf;

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
    private const WAIT_TIME_SECONDS = 'WaitTimeSeconds';
    private const DELAY_SECONDS = 'DelaySeconds';
    // SQS allows maximum message delay of 15 minutes
    private const MAX_DELAY_SECONDS = 15 * 60;

    private string $s3BucketName;

    private SqsClientFactoryInterface $sqsClientFactory;

    private S3ClientFactoryInterface $s3ClientFactory;

    private S3MessageKeyGeneratorInterface $s3MessageKeyGenerator;

    private SqsClient $sqsClient;

    private S3Client $s3Client;

    private LoggerInterface $logger;

    /**
     * -1 = no limit
     */
    private int $consumeLoopIterationsCount;


    public function __construct(
        string $s3BucketName,
        SqsClientFactoryInterface $sqsClientFactory,
        S3ClientFactoryInterface $s3ClientFactory,
        S3MessageKeyGeneratorInterface $s3MessageKeyGenerator,
        LoggerInterface $logger,
        int $consumeLoopIterationsCount = -1
    ) {
        $this->s3BucketName = $s3BucketName;
        $this->sqsClientFactory = $sqsClientFactory;
        $this->sqsClient = $this->sqsClientFactory->create();
        $this->s3ClientFactory = $s3ClientFactory;
        $this->s3Client = $this->s3ClientFactory->create();
        $this->s3MessageKeyGenerator = $s3MessageKeyGenerator;
        $this->logger = $logger;
        $this->consumeLoopIterationsCount = $consumeLoopIterationsCount;
    }


    public function getConsumeLoopIterationsCount(): int
    {
        return $this->consumeLoopIterationsCount;
    }


    /**
     * @param array<mixed> $awsResultMessages
     *
     * @return SqsMessage[]
     */
    public function fromAwsResultMessages(array $awsResultMessages, string $queueUrl): array
    {
        /**
         * @var SqsMessage[]
         */
        $sqsMessages = [];

        assert($queueUrl !== '');

        foreach ($awsResultMessages as $message) {
            $decodedMessageBody = json_decode($message[SqsMessageFields::BODY]);

            if (is_array($decodedMessageBody)) { /* message stored in S3 */
                if (S3Pointer::isS3Pointer($decodedMessageBody)) {
                    $bucketName = S3Pointer::getBucketNameFromValidS3Pointer($decodedMessageBody);
                    $s3Key = S3Pointer::getS3KeyFromValidS3Pointer($decodedMessageBody);

                    $this->logger->info(
                        sprintf(
                            'Message with ID %s will be downloaded from S3 bucket: %s. Key: %s',
                            $message[SqsMessageFields::MESSAGE_ID],
                            $bucketName,
                            $s3Key,
                        ),
                        [
                            LoggerContextField::JOB_QUEUE_NAME => $queueUrl,
                            LoggerContextField::MESSAGE_ID => $message[SqsMessageFields::MESSAGE_ID],
                        ],
                    );

                    $s3Object = $this->s3Client->getObject([
                        'Bucket' => $bucketName,
                        'Key' => $s3Key,
                    ]);
                    $s3ObjectBody = $s3Object->get('Body');
                    assert($s3ObjectBody instanceof Stream);

                    // convert Stream into string content
                    // see https://stackoverflow.com/questions/13686316/grabbing-contents-of-object-from-s3-via-php-sdk-2
                    $content = (string)$s3ObjectBody;
                    $message[SqsMessageFields::BODY] = $content;
                }
            }

            $sqsMessages[] = new SqsMessage($message, $queueUrl);
        }

        return $sqsMessages;
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
                if ($messages !== null && count($messages) > 0) {
                    $sqsMessages = $this->fromAwsResultMessages($messages, $queueName);
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
                    [
                        LoggerContextField::EXCEPTION => (string)$exception,
                        LoggerContextField::JOB_QUEUE_NAME => $queueName,
                    ],
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
     * @param array<mixed> $properties
     *
     * @throws AwsException
     * @throws SqsClientException
     */
    private function publishMessage(
        string $messageBody,
        string $queueName,
        array $properties = []
    ): void {
        $delaySeconds = (int)($properties[self::DELAY_SECONDS] ?? 0);

        if ($delaySeconds < 0 || $delaySeconds > self::MAX_DELAY_SECONDS) {
            throw SqsClientException::createFromInvalidDelaySeconds($delaySeconds);
        }

        if (SqsMessage::isTooBig($messageBody)) {
            $key = $this->s3MessageKeyGenerator->generate();
            $receipt = $this->s3Client->upload(
                $this->s3BucketName,
                $key,
                $messageBody,
            );

            // Swap the message for a pointer to the actual message in S3.
            $messageBody = (string)(new S3Pointer($this->s3BucketName, $key, $receipt));
        }

        $messageToSend = [
            SqsSendingMessageFields::DELAY_SECONDS => $delaySeconds,
            SqsSendingMessageFields::MESSAGE_ATTRIBUTES => [
                SqsSendingMessageFields::QUEUE_URL => [
                    'DataType' => 'String',
                    // queueName might be handy here if we want to consume
                    // from multiple queues in parallel via promises.
                    // Then we need queue in message directly so that we can delete it.
                    'StringValue' => $queueName,
                ],
            ],
            SqsSendingMessageFields::MESSAGE_BODY => $messageBody,
            SqsSendingMessageFields::QUEUE_URL => $queueName,
        ];

        try {
            $this->sqsClient->sendMessage($messageToSend);
        } catch (AwsException $exception) {
            $this->reconnect($exception, $queueName);
            $this->sqsClient->sendMessage($messageToSend);
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
                LoggerContextField::JOB_QUEUE_NAME => $queueName,
                LoggerContextField::EXCEPTION => (string)$exception,
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
