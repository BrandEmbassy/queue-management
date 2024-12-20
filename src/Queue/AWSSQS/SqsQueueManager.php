<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Aws\Sqs\SqsClient;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\JobType;
use BE\QueueManagement\Logging\LoggerContextField;
use BE\QueueManagement\Logging\LoggerHelper;
use BE\QueueManagement\Observability\AfterExecutionPlannedEvent;
use BE\QueueManagement\Observability\AfterMessageSentEvent;
use BE\QueueManagement\Observability\BeforeExecutionPlannedEvent;
use BE\QueueManagement\Observability\BeforeMessageSentEvent;
use BE\QueueManagement\Observability\PlannedExecutionStrategyEnum;
use BE\QueueManagement\Queue\QueueManagerInterface;
use BrandEmbassy\DateTime\DateTimeFormatter;
use BrandEmbassy\DateTime\DateTimeImmutableFactory;
use DateTimeImmutable;
use GuzzleHttp\Psr7\Stream;
use LogicException;
use Nette\Utils\Json;
use Nette\Utils\Validators;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function assert;
use function count;
use function is_array;
use function is_string;
use function json_decode;
use function preg_replace;
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

    // SQS allows maximum message delay of 15 minutes
    public const MAX_DELAY_IN_SECONDS = 15 * 60;

    private const WAIT_TIME_SECONDS = 'WaitTimeSeconds';

    private const DELAY_SECONDS = 'DelaySeconds';

    private string $s3BucketName;

    private SqsClientFactoryInterface $sqsClientFactory;

    private S3ClientFactoryInterface $s3ClientFactory;

    private MessageKeyGeneratorInterface $messageKeyGenerator;

    private SqsClient $sqsClient;

    private S3Client $s3Client;

    private LoggerInterface $logger;

    private int $consumeLoopIterationsCount;

    private string $queueNamePrefix;

    private DateTimeImmutableFactory $dateTimeImmutableFactory;

    private ?DelayedJobSchedulerInterface $delayedJobScheduler;

    private ?EventDispatcherInterface $eventDispatcher;

    private readonly SqsMessageAttributeFactory $sqsMessageAttributeFactory;

    private bool $isBeingTerminated = false;


    public function __construct(
        string $s3BucketName,
        SqsClientFactoryInterface $sqsClientFactory,
        S3ClientFactoryInterface $s3ClientFactory,
        MessageKeyGeneratorInterface $messageKeyGenerator,
        LoggerInterface $logger,
        DateTimeImmutableFactory $dateTimeImmutableFactory,
        ?DelayedJobSchedulerInterface $delayedJobScheduler = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        int $consumeLoopIterationsCount = self::CONSUME_LOOP_ITERATIONS_NO_LIMIT,
        string $queueNamePrefix = ''
    ) {
        $this->s3BucketName = $s3BucketName;
        $this->sqsClientFactory = $sqsClientFactory;
        $this->sqsClient = $this->sqsClientFactory->create();
        $this->s3ClientFactory = $s3ClientFactory;
        $this->s3Client = $this->s3ClientFactory->create();
        $this->messageKeyGenerator = $messageKeyGenerator;
        $this->logger = $logger;
        $this->consumeLoopIterationsCount = $consumeLoopIterationsCount;
        $this->queueNamePrefix = $queueNamePrefix;
        $this->dateTimeImmutableFactory = $dateTimeImmutableFactory;
        $this->delayedJobScheduler = $delayedJobScheduler;
        $this->eventDispatcher = $eventDispatcher;
        $this->sqsMessageAttributeFactory = new SqsMessageAttributeFactory();
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
        /** @var SqsMessage[] */
        $sqsMessages = [];

        assert($queueUrl !== '');

        foreach ($awsResultMessages as $message) {
            $decodedMessageBody = json_decode($message[SqsMessageFields::BODY]);

            /* message stored in S3 */
            if (is_array($decodedMessageBody) && S3Pointer::isS3Pointer($decodedMessageBody)) {
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

            foreach ($message[SqsMessageFields::MESSAGE_ATTRIBUTES] ?? [] as $messageAttributeName => $messageAttributeValue) {
                $message[SqsMessageFields::MESSAGE_ATTRIBUTES][$messageAttributeName] = $this->sqsMessageAttributeFactory->createFromArray($messageAttributeName, $messageAttributeValue);
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

        while (!$this->isBeingTerminated
            && (
                !$isLoopIterationsLimitEnabled
                || $loopIterationsCounter < $this->consumeLoopIterationsCount
            )
        ) {
            try {
                $result = $this->sqsClient->receiveMessage([
                    'AttributeNames' => ['All'],
                    'MaxNumberOfMessages' => $maxNumberOfMessages,
                    'MessageAttributeNames' => ['All'],
                    'QueueUrl' => $prefixedQueueName,
                    'WaitTimeSeconds' => $waitTimeSeconds,
                ]);

                $messages = $result->get('Messages');
                $this->processAwsMessages($messages, $prefixedQueueName, $consumer);

                $loopIterationsCounter = $isLoopIterationsLimitEnabled ? $loopIterationsCounter + 1 : $loopIterationsCounter;
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

        $sqsMessageId = $this->publishMessage($job, $prefixedQueueName);
        LoggerHelper::logJobPushedIntoQueue(
            $job,
            $prefixedQueueName,
            $this->logger,
            JobType::SQS,
            LoggerHelper::NOT_DELAYED,
            $sqsMessageId,
        );
    }


    public function pushDelayedWithMilliseconds(JobInterface $job, int $delayInMilliseconds): void
    {
        $this->pushDelayed($job, $delayInMilliseconds / 1000);
    }


    /**
     * @param int $maxDelayInSeconds This parameter can be used to override the default maximum delay before using
     *                               delayed job scheduler (if one is configured). This can be useful for
     *                               implementation of automated tests & synthetic monitoring of delayed job
     *                               scheduler on live environments while maintaining quick feedback loop.
     */
    public function pushDelayed(JobInterface $job, int $delayInSeconds, ?int $maxDelayInSeconds = self::MAX_DELAY_IN_SECONDS): void
    {
        assert(
            $maxDelayInSeconds === null || $maxDelayInSeconds >= 0,
            'If argument $maxDelayInSeconds is specified, it must be greater or equal to 0',
        );

        $prefixedQueueName = $this->getPrefixedQueueName($job->getJobDefinition()->getQueueName());

        $executionPlannedAt = $this->dateTimeImmutableFactory->getNow()->modify(
            sprintf('+ %d seconds', $delayInSeconds),
        );
        $job->setExecutionPlannedAt($executionPlannedAt);

        $finalDelayInSeconds = $delayInSeconds;

        if ($maxDelayInSeconds === null) {
            $this->planExecutionUsingSqsDeliveryDelay($job, $prefixedQueueName, $delayInSeconds, $finalDelayInSeconds);

            return;
        }

        if ($delayInSeconds > $maxDelayInSeconds) {
            if ($this->delayedJobScheduler !== null) {
                $this->scheduleJob($job, $prefixedQueueName, $executionPlannedAt, $delayInSeconds, $maxDelayInSeconds);

                return;
            }

            $this->logger->info(
                'Requested delay is greater than SQS limit. Job execution has been planned and will be requeued until then.',
                [
                    'executionPlannedAt' => DateTimeFormatter::format($executionPlannedAt),
                    'delayInSeconds' => $delayInSeconds,
                    'maxDelayInSeconds' => $maxDelayInSeconds,
                    LoggerContextField::JOB_QUEUE_NAME => $prefixedQueueName,
                    LoggerContextField::JOB_UUID => $job->getUuid(),
                ],
            );

            $finalDelayInSeconds = self::MAX_DELAY_IN_SECONDS;
        }

        $this->planExecutionUsingSqsDeliveryDelay($job, $prefixedQueueName, $delayInSeconds, $finalDelayInSeconds);
    }


    private function planExecutionUsingSqsDeliveryDelay(
        JobInterface $job,
        string $prefixedQueueName,
        int $delayInSeconds,
        int $finalDelayInSeconds
    ): void {
        $parameters = [self::DELAY_SECONDS => $finalDelayInSeconds];

        $beforeExecutionPlannedEvent = null;
        if ($this->eventDispatcher !== null) {
            $beforeExecutionPlannedEvent = new BeforeExecutionPlannedEvent(
                Uuid::uuid4(),
                $job,
                $prefixedQueueName,
                $delayInSeconds,
                PlannedExecutionStrategyEnum::SQS_DELIVERY_DELAY,
            );
            $this->eventDispatcher->dispatch($beforeExecutionPlannedEvent);
        }

        $sqsMessageId = $this->publishMessage($job, $prefixedQueueName, $parameters);

        $this->eventDispatcher?->dispatch(new AfterExecutionPlannedEvent(
            $beforeExecutionPlannedEvent->executionPlannedId ?? Uuid::uuid4(),
            $job,
            $prefixedQueueName,
            $delayInSeconds,
            PlannedExecutionStrategyEnum::SQS_DELIVERY_DELAY,
            null,
            $sqsMessageId,
        ));

        LoggerHelper::logJobPushedIntoQueue(
            $job,
            $prefixedQueueName,
            $this->logger,
            JobType::SQS,
            $finalDelayInSeconds,
            $sqsMessageId,
        );
    }


    private function scheduleJob(
        JobInterface $job,
        string $prefixedQueueName,
        DateTimeImmutable $executionPlannedAt,
        int $delayInSeconds,
        int $maxDelayInSeconds
    ): void {
        assert($this->delayedJobScheduler !== null, 'Delayed job scheduler must be set to schedule a job.');

        $beforeExecutionPlannedEvent = null;
        if ($this->eventDispatcher !== null) {
            $beforeExecutionPlannedEvent = new BeforeExecutionPlannedEvent(
                Uuid::uuid4(),
                $job,
                $prefixedQueueName,
                $delayInSeconds,
                PlannedExecutionStrategyEnum::DELAYED_JOB_SCHEDULER,
            );
            $this->eventDispatcher->dispatch($beforeExecutionPlannedEvent);
        }

        $scheduledEventId = $this->delayedJobScheduler->scheduleJob($job, $prefixedQueueName);

        $this->logger->info(
            sprintf(
                'Requested delay is greater than SQS limit. Job execution has been planned using %s.',
                $this->delayedJobScheduler->getSchedulerName(),
            ),
            [
                'executionPlannedAt' => DateTimeFormatter::format($executionPlannedAt),
                'scheduledEventId' => $scheduledEventId,
                'delayInSeconds' => $delayInSeconds,
                'maxDelayInSeconds' => $maxDelayInSeconds,
                LoggerContextField::JOB_QUEUE_NAME => $prefixedQueueName,
                LoggerContextField::JOB_UUID => $job->getUuid(),
            ],
        );

        $this->eventDispatcher?->dispatch(new AfterExecutionPlannedEvent(
            $beforeExecutionPlannedEvent->executionPlannedId ?? Uuid::uuid4(),
            $job,
            $prefixedQueueName,
            $delayInSeconds,
            PlannedExecutionStrategyEnum::DELAYED_JOB_SCHEDULER,
            $scheduledEventId,
            null,
        ));
    }


    /**
     * @param array<mixed> $properties
     *
     * @throws AwsException
     * @throws SqsClientException
     */
    private function publishMessage(
        JobInterface $job,
        string $prefixedQueueName,
        array $properties = []
    ): string {
        $messageBody = $job->toJson();

        // Remove invalid XML characters because AWS SQS supports only valid XML characters.
        $messageBody = preg_replace(
            '/[^\x{9}\x{a}\x{d}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]+/u',
            '',
            $messageBody,
        );
        assert(is_string($messageBody));

        $delaySeconds = (int)($properties[self::DELAY_SECONDS] ?? 0);

        if ($delaySeconds < 0 || $delaySeconds > self::MAX_DELAY_IN_SECONDS) {
            throw SqsClientException::createFromInvalidDelaySeconds($delaySeconds);
        }

        $this->eventDispatcher?->dispatch(new BeforeMessageSentEvent(
            $job,
            $delaySeconds,
            $prefixedQueueName,
        ));

        // queueName might be handy here if we want to consume
        // from multiple queues in parallel via promises.
        // Then we need queue in message directly so that we can delete it.
        $job->setMessageAttribute(
            new SqsMessageAttribute(
                SqsSendingMessageFields::QUEUE_URL,
                $prefixedQueueName,
                SqsMessageAttributeDataType::STRING,
            ),
        );

        $messageAttributes = $job->getMessageAttributes();

        if (SqsMessage::isTooBig($messageBody, $messageAttributes)) {
            $key = $this->messageKeyGenerator->generate($job);
            $receipt = $this->s3Client->upload(
                $this->s3BucketName,
                $key,
                $messageBody,
            );

            // Swap the message for a pointer to the actual message in S3.
            $messageBody = (string)(new S3Pointer($this->s3BucketName, $key, $receipt));
        }

        $normalizedMessageAttributes = [];
        foreach ($messageAttributes as $messageAttribute) {
            $normalizedMessageAttributes[$messageAttribute->getName()] = $messageAttribute->toArray();
        }

        $messageToSend = [
            SqsSendingMessageFields::DELAY_SECONDS => $delaySeconds,
            SqsSendingMessageFields::MESSAGE_ATTRIBUTES => $normalizedMessageAttributes,
            SqsSendingMessageFields::MESSAGE_BODY => $messageBody,
            SqsSendingMessageFields::QUEUE_URL => $prefixedQueueName,
        ];

        try {
            return $this->sendMessage($messageToSend);
        } catch (AwsException $exception) {
            $this->reconnect($exception, $prefixedQueueName);

            return $this->sendMessage($messageToSend);
        }
    }


    /**
     * @param array<string, mixed> $messageToSend
     */
    private function sendMessage(array $messageToSend): string
    {
        $result = $this->sqsClient->sendMessage($messageToSend);
        $messageId = $result->get(SqsMessageFields::MESSAGE_ID);

        $this->eventDispatcher?->dispatch(new AfterMessageSentEvent(
            $messageToSend[SqsSendingMessageFields::DELAY_SECONDS],
            $messageId,
            $messageToSend[SqsSendingMessageFields::MESSAGE_ATTRIBUTES],
            $messageToSend[SqsSendingMessageFields::MESSAGE_BODY],
        ));

        return $messageId;
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


    public function terminateGracefully(): void
    {
        $this->writeDebugLog('SqsQueueManager::terminateGracefully() reached');

        $this->isBeingTerminated = true;

        $this->writeDebugLog('SqsQueueManager::isBeingTerminated set to ' . Json::encode($this->isBeingTerminated));
    }


    private function writeDebugLog(string $message): void
    {
        $this->logger->debug('Gracefully terminating command: ' . $message);
    }


    /**
     * @param mixed[]|null $messages
     */
    private function processAwsMessages(?array $messages, string $prefixedQueueName, callable $consumer): void
    {
        if ($messages === null || count($messages) <= 0) {
            return;
        }

        $sqsMessages = $this->fromAwsResultMessages($messages, $prefixedQueueName);
        foreach ($sqsMessages as $sqsMessage) {
            $consumer($sqsMessage);
        }
    }
}
