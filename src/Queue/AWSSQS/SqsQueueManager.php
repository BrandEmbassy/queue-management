<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Aws\Sqs\SqsClient;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\JobParameters;
use BE\QueueManagement\Jobs\JobType;
use BE\QueueManagement\Logging\LoggerContextField;
use BE\QueueManagement\Logging\LoggerHelper;
use BE\QueueManagement\Queue\QueueManagerInterface;
use BE\QueueManagement\Queue\QueueWorkerState;
use BrandEmbassy\DateTime\DateTimeFormatter;
use BrandEmbassy\DateTime\DateTimeImmutableFactory;
use GuzzleHttp\Psr7\Stream;
use LogicException;
use Nette\Utils\Json;
use Nette\Utils\Validators;
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
    public const CONSUME_LOOP_ITERATIONS_NO_LIMIT = -1;
    private const WAIT_TIME_SECONDS = 'WaitTimeSeconds';
    private const DELAY_SECONDS = 'DelaySeconds';
    // SQS allows maximum message delay of 15 minutes
    private const MAX_DELAY_SECONDS = 15 * 60;

    private string $s3BucketName;

    private SqsClientFactoryInterface $sqsClientFactory;

    private S3ClientFactoryInterface $s3ClientFactory;

    private MessageKeyGeneratorInterface $messageKeyGenerator;

    private SqsClient $sqsClient;

    private S3Client $s3Client;

    private QueueWorkerState $queueWorkerState;

    private LoggerInterface $logger;

    private int $consumeLoopIterationsCount;

    private string $queueNamePrefix;

    private DateTimeImmutableFactory $dateTimeImmutableFactory;


    public function __construct(
        string $s3BucketName,
        SqsClientFactoryInterface $sqsClientFactory,
        S3ClientFactoryInterface $s3ClientFactory,
        MessageKeyGeneratorInterface $messageKeyGenerator,
        QueueWorkerState $queueWorkerState,
        LoggerInterface $logger,
        DateTimeImmutableFactory $dateTimeImmutableFactory,
        int $consumeLoopIterationsCount = self::CONSUME_LOOP_ITERATIONS_NO_LIMIT,
        string $queueNamePrefix = ''
    ) {
        $this->s3BucketName = $s3BucketName;
        $this->sqsClientFactory = $sqsClientFactory;
        $this->sqsClient = $this->sqsClientFactory->create();
        $this->s3ClientFactory = $s3ClientFactory;
        $this->s3Client = $this->s3ClientFactory->create();
        $this->messageKeyGenerator = $messageKeyGenerator;
        $this->queueWorkerState = $queueWorkerState;
        $this->logger = $logger;
        $this->consumeLoopIterationsCount = $consumeLoopIterationsCount;
        $this->queueNamePrefix = $queueNamePrefix;
        $this->dateTimeImmutableFactory = $dateTimeImmutableFactory;
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
        $prefixedQueueName = $this->getPrefixedQueueName($queueName);

        $maxNumberOfMessages = (int)($parameters[self::MAX_NUMBER_OF_MESSAGES] ?? 10);
        $waitTimeSeconds = (int)($parameters[self::WAIT_TIME_SECONDS] ?? 10);

        $loopIterationsCounter = 0;
        $isLoopIterationsLimitEnabled = $this->consumeLoopIterationsCount !== self::CONSUME_LOOP_ITERATIONS_NO_LIMIT;

        while (!$isLoopIterationsLimitEnabled || $loopIterationsCounter < $this->consumeLoopIterationsCount) {
            if ($this->queueWorkerState->shouldStop()) {
                $this->logger->debug(
                    'Processing of SQS queue is going to shut down gracefully.',
                    [
                        LoggerContextField::JOB_QUEUE_NAME => $prefixedQueueName,
                    ],
                );
                break;
            }
            try {
                $result = $this->sqsClient->receiveMessage([
                    'AttributeNames' => ['All'],
                    'MaxNumberOfMessages' => $maxNumberOfMessages,
                    'MessageAttributeNames' => ['All'],
                    'QueueUrl' => $prefixedQueueName,
                    'WaitTimeSeconds' => $waitTimeSeconds,
                ]);

                $messages = $result->get('Messages');
                if ($messages !== null && count($messages) > 0) {
                    $sqsMessages = $this->fromAwsResultMessages($messages, $prefixedQueueName);
                    foreach ($sqsMessages as $sqsMessage) {
                        $consumer($sqsMessage);
                    }
                }
                if ($isLoopIterationsLimitEnabled) {
                    $loopIterationsCounter++;
                }
            } catch (AwsException $exception) {
                $this->logger->warning(
                    'AwsException: ' . $exception->getMessage(),
                    [
                        LoggerContextField::EXCEPTION => $exception,
                        LoggerContextField::JOB_QUEUE_NAME => $prefixedQueueName,
                    ],
                );

                $this->reconnect($exception, $prefixedQueueName);
            }
        }
    }


    public function push(JobInterface $job): void
    {
        $prefixedQueueName = $this->getPrefixedQueueName($job->getJobDefinition()->getQueueName());

        $this->publishMessage($job->toJson(), $prefixedQueueName);
        LoggerHelper::logJobPushedIntoQueue($job, $prefixedQueueName, $this->logger, JobType::get(JobType::SQS));
    }


    public function pushDelayedWithMilliseconds(JobInterface $job, int $delayInMilliseconds): void
    {
        $this->pushDelayed($job, $delayInMilliseconds / 1000);
    }


    public function pushDelayed(JobInterface $job, int $delayInSeconds): void
    {
        $prefixedQueueName = $this->getPrefixedQueueName($job->getJobDefinition()->getQueueName());

        if ($delayInSeconds > self::MAX_DELAY_SECONDS) {
            $executionPlannedAt = $this->dateTimeImmutableFactory->getNow()->modify(
                sprintf('+ %d seconds', $delayInSeconds),
            );
            $this->logger->info(
                'Requested delay is greater than SQS limit. Job execution has been planned and will be requeued until then.',
                ['executionPlannedAt' => DateTimeFormatter::format($executionPlannedAt)],
            );
            $job->executionPlanned($executionPlannedAt);
            $delayInSeconds = self::MAX_DELAY_SECONDS;
        }

        $parameters = [self::DELAY_SECONDS => $delayInSeconds];

        $this->publishMessage($this->getJobJson($job), $prefixedQueueName, $parameters);
        LoggerHelper::logJobPushedIntoQueue($job, $prefixedQueueName, $this->logger, JobType::get(JobType::SQS), $delayInSeconds);
    }


    /**
     * @param array<mixed> $properties
     *
     * @throws AwsException
     * @throws SqsClientException
     */
    private function publishMessage(
        string $messageBody,
        string $prefixedQueueName,
        array $properties = []
    ): void {
        $delaySeconds = (int)($properties[self::DELAY_SECONDS] ?? 0);

        if ($delaySeconds < 0 || $delaySeconds > self::MAX_DELAY_SECONDS) {
            throw SqsClientException::createFromInvalidDelaySeconds($delaySeconds);
        }

        if (SqsMessage::isTooBig($messageBody)) {
            $key = $this->messageKeyGenerator->generate();
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
                    'StringValue' => $prefixedQueueName,
                ],
            ],
            SqsSendingMessageFields::MESSAGE_BODY => $messageBody,
            SqsSendingMessageFields::QUEUE_URL => $prefixedQueueName,
        ];

        try {
            $this->sqsClient->sendMessage($messageToSend);
        } catch (AwsException $exception) {
            $this->reconnect($exception, $prefixedQueueName);
            $this->sqsClient->sendMessage($messageToSend);
        }
    }


    /**
     * @throws SqsClientException
     */
    private function reconnect(Throwable $exception, string $prefixedQueueName): void
    {
        $this->sqsClient = $this->sqsClientFactory->create();
        $this->s3Client = $this->s3ClientFactory->create();

        $this->logger->warning(
            'Reconnecting: ' . $exception->getMessage(),
            [
                LoggerContextField::JOB_QUEUE_NAME => $prefixedQueueName,
                LoggerContextField::EXCEPTION => $exception,
            ],
        );
    }


    public function checkConnection(): bool
    {
        // No checkConn method in SqsClient. For now just providing fake response
        // in the future we might want to check somehow whether we still have connectivity.
        return true;
    }


    private function getPrefixedQueueName(string $queueName): string
    {
        $prefixedQueueName = $this->queueNamePrefix . $queueName;

        if (!Validators::isUrl($prefixedQueueName)) {
            throw new LogicException(
                'In SQS, queue name is supposed to be a URL, "' . $prefixedQueueName . '" provided instead. '
                . 'A prefix can be used to prepend a common part of the URL to uniquely named queues.',
            );
        }

        return $prefixedQueueName;
    }


    private function getJobJson(JobInterface $job): string
    {
        if ($job->getExecutionPlannedAt() === null) {
            return $job->toJson();
        }

        $jobJson = Json::decode($job->toJson(), Json::FORCE_ARRAY);

        if (isset($jobJson[JobParameters::EXECUTION_PLANNED_AT])) {
            throw new LogicException('JobInterface::toJson() must not return key "' . JobParameters::EXECUTION_PLANNED_AT . '".');
        }

        $jobJson[JobParameters::EXECUTION_PLANNED_AT] = DateTimeFormatter::format($job->getExecutionPlannedAt());

        return Json::encode($jobJson);
    }
}
