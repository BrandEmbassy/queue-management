<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\Sqs\SqsClient;
use BE\QueueManagement\Jobs\Execution\ConsumerFailedExceptionInterface;
use BE\QueueManagement\Jobs\Execution\DelayableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Execution\JobExecutorInterface;
use BE\QueueManagement\Jobs\Execution\JobLoaderInterface;
use BE\QueueManagement\Jobs\Execution\UnresolvableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\FailResolving\PushDelayedResolver;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Logging\LoggerContextField;
use BE\QueueManagement\Logging\LoggerHelper;
use BE\QueueManagement\Queue\AWSSQS\MessageDeduplication\MessageDeduplication;
use BE\QueueManagement\Queue\JobExecutionStatus;
use BE\QueueManagement\Queue\QueueManagerInterface;
use BrandEmbassy\DateTime\DateTimeImmutableFactory;
use Psr\Log\LoggerInterface;
use Tracy\Debugger;
use function round;
use function sprintf;

/**
 * @final
 */
class SqsConsumer implements SqsConsumerInterface
{
    protected LoggerInterface $logger;

    protected PushDelayedResolver $pushDelayedResolver;

    protected JobExecutorInterface $jobExecutor;

    protected JobLoaderInterface $jobLoader;

    protected SqsClient $sqsClient;

    protected MessageDeduplication $messageDeduplication;

    protected DateTimeImmutableFactory $dateTimeImmutableFactory;

    protected QueueManagerInterface $queueManager;


    public function __construct(
        LoggerInterface $logger,
        JobExecutorInterface $jobExecutor,
        PushDelayedResolver $pushDelayedResolver,
        JobLoaderInterface $jobLoader,
        SqsClient $sqsClient,
        MessageDeduplication $messageDeduplication,
        DateTimeImmutableFactory $dateTimeImmutableFactory,
        QueueManagerInterface $queueManager
    ) {
        $this->logger = $logger;
        $this->pushDelayedResolver = $pushDelayedResolver;
        $this->jobExecutor = $jobExecutor;
        $this->jobLoader = $jobLoader;
        $this->sqsClient = $sqsClient;
        $this->messageDeduplication = $messageDeduplication;
        $this->dateTimeImmutableFactory = $dateTimeImmutableFactory;
        $this->queueManager = $queueManager;
    }


    public function __invoke(SqsMessage $message): JobExecutionStatus
    {
        Debugger::timer('job-execution');
        try {
            if ($this->messageDeduplication->isDuplicate($message)) {
                $this->logger->warning(
                    'Duplicate message detected',
                    [
                        LoggerContextField::MESSAGE_ID => $message->getMessageId(),
                        LoggerContextField::MESSAGE_BODY => $message->getBody(),
                        LoggerContextField::JOB_QUEUE_NAME => $message->getQueueUrl(),
                    ],
                );
                $this->deleteMessageFromQueue($message);

                return JobExecutionStatus::REMOVED_DUPLICATE;
            }

            $jobExecutionStatus = $this->executeJob($message);
            $this->deleteMessageFromQueue($message);

            return $jobExecutionStatus;
        } catch (ConsumerFailedExceptionInterface $exception) {
            // do not delete message.
            // After visibility timeout message should be visible to other consumers.
            $this->logger->error(
                'Consumer failed, job requeued: ' . $exception->getMessage(),
                [
                    LoggerContextField::EXCEPTION => $exception,
                    LoggerContextField::MESSAGE_BODY => $message->getBody(),
                    LoggerContextField::MESSAGE_ID => $message->getMessageId(),
                    LoggerContextField::JOB_QUEUE_NAME => $message->getQueueUrl(),
                ],
            );

            throw $exception;
        } catch (UnresolvableProcessFailExceptionInterface $exception) {
            $this->logger->warning(
                'Job removed from queue: ' . $exception->getMessage(),
                [
                    LoggerContextField::EXCEPTION => $exception,
                    LoggerContextField::MESSAGE_BODY => $message->getBody(),
                    LoggerContextField::MESSAGE_ID => $message->getMessageId(),
                    LoggerContextField::JOB_QUEUE_NAME => $message->getQueueUrl(),
                ],
            );

            $this->deleteMessageFromQueue($message);

            return JobExecutionStatus::FAILED_UNRESOLVABLE;
        }
    }


    private function deleteMessageFromQueue(SqsMessage $message): void
    {
        $this->sqsClient->deleteMessage([
            SqsMessageFields::QUEUE_URL => $message->getQueueUrl(),
            SqsMessageFields::RECEIPT_HANDLE => $message->getReceiptHandle(),
        ]);

        $this->logger->info(
            'Message deleted from queue',
            [
                LoggerContextField::MESSAGE_ID => $message->getMessageId(),
                LoggerContextField::MESSAGE_BODY => $message->getBody(),
                LoggerContextField::JOB_QUEUE_NAME => $message->getQueueUrl(),
            ],
        );
    }


    private function executeJob(SqsMessage $message): JobExecutionStatus
    {
        try {
            $job = $this->jobLoader->loadJob($message->getBody());
            $jobExecutionPlannedAt = $job->getExecutionPlannedAt();

            if ($jobExecutionPlannedAt !== null) {
                $now = $this->dateTimeImmutableFactory->getNow();
                $timeRemainsInSeconds = $jobExecutionPlannedAt->getTimestamp() - $now->getTimestamp();

                if ($timeRemainsInSeconds > 0) {
                    $this->logSqsDelayJob($job, $timeRemainsInSeconds);
                    $this->queueManager->pushDelayed(
                        $job,
                        $timeRemainsInSeconds,
                        SqsQueueManager::MAX_DELAY_IN_SECONDS,
                    );

                    return JobExecutionStatus::DELAYED_NOT_PLANNED_YET;
                }
            }

            $this->jobExecutor->execute($job);

            return JobExecutionStatus::SUCCESS;
        } catch (DelayableProcessFailExceptionInterface $exception) {
            LoggerHelper::logDelayableProcessFailException($exception, $this->logger);

            $this->pushDelayedResolver->resolve($exception->getJob(), $exception);

            return JobExecutionStatus::FAILED_DELAYED;
        }
    }


    private function logSqsDelayJob(JobInterface $job, int $delay): void
    {
        $this->logger->info(
            sprintf("Job requeued, it's not planned to be executed yet. [delay: %d]", $delay),
            [
                LoggerContextField::JOB_UUID => $job->getUuid(),
                LoggerContextField::JOB_NAME => $job->getName(),
                LoggerContextField::JOB_QUEUE_NAME => $job->getJobDefinition()->getQueueName(),
                LoggerContextField::JOB_EXECUTION_TIME => round(Debugger::timer('job-execution') * 1000, 5),
            ],
        );
    }
}
