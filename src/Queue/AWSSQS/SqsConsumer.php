<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\Sqs\SqsClient;
use BE\QueueManagement\Jobs\Execution\ConsumerFailedExceptionInterface;
use BE\QueueManagement\Jobs\Execution\DelayableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Execution\JobExecutorInterface;
use BE\QueueManagement\Jobs\Execution\JobLoaderInterface;
use BE\QueueManagement\Jobs\Execution\UnresolvableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\FailResolving\PushDelayedResolver;
use BE\QueueManagement\Logging\LoggerContextField;
use BE\QueueManagement\Logging\LoggerHelper;
use Psr\Log\LoggerInterface;

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


    public function __construct(
        LoggerInterface $logger,
        JobExecutorInterface $jobExecutor,
        PushDelayedResolver $pushDelayedResolver,
        JobLoaderInterface $jobLoader,
        SqsClient $sqsClient,
        MessageDeduplication $messageDeduplication
    ) {
        $this->logger = $logger;
        $this->pushDelayedResolver = $pushDelayedResolver;
        $this->jobExecutor = $jobExecutor;
        $this->jobLoader = $jobLoader;
        $this->sqsClient = $sqsClient;
        $this->messageDeduplication = $messageDeduplication;
    }


    public function __invoke(SqsMessage $message): void
    {
        try {
            if ($this->messageDeduplication->isDuplicate($message)) {
                $this->logger->warning('Duplicate message detected: ' . $message->getBody());
                $this->deleteSqsMessage($message);

                return;
            }

            $this->executeJob($message);
            $this->deleteSqsMessage($message);
        } catch (ConsumerFailedExceptionInterface $exception) {
            // do not delete message.
            // After visibility timeout message should be visible to other consumers.
            $this->logger->error(
                'Consumer failed, job requeued: ' . $exception->getMessage(),
                [LoggerContextField::EXCEPTION => $exception],
            );

            throw $exception;
        } catch (UnresolvableProcessFailExceptionInterface $exception) {
            $this->logger->warning(
                'Job removed from queue: ' . $exception->getMessage(),
                [LoggerContextField::EXCEPTION => $exception],
            );

            $this->deleteSqsMessage($message);
        }
    }


    private function deleteSqsMessage(SqsMessage $message): void
    {
        $this->sqsClient->deleteMessage([
            SqsMessageFields::QUEUEURL => $message->getQueueUrl(),
            SqsMessageFields::RECEIPTHANDLE => $message->getReceiptHandle(),
        ]);
    }


    private function executeJob(SqsMessage $message): void
    {
        try {
            $job = $this->jobLoader->loadJob($message->getBody());

            $this->jobExecutor->execute($job);
        } catch (DelayableProcessFailExceptionInterface $exception) {
            LoggerHelper::logDelayableProcessFailException($exception, $this->logger);

            $this->pushDelayedResolver->resolve($exception->getJob(), $exception);
        }
    }
}
