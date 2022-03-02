<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Aws\Sqs\SqsClient;
use BE\QueueManagement\Jobs\Execution\ConsumerFailedExceptionInterface;
use BE\QueueManagement\Jobs\Execution\DelayableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Execution\JobExecutorInterface;
use BE\QueueManagement\Jobs\Execution\JobLoaderInterface;
use BE\QueueManagement\Jobs\Execution\UnresolvableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\FailResolving\PushDelayedResolver;
use BE\QueueManagement\Queue\Common\CommonUtils;
use Psr\Log\LoggerInterface;

class SqsConsumer implements SqsConsumerInterface
{
    protected LoggerInterface $logger;

    protected PushDelayedResolver $pushDelayedResolver;

    protected JobExecutorInterface $jobExecutor;

    protected JobLoaderInterface $jobLoader;

    protected SqsClient $sqsClient;

    protected MessageDeduplicationInterface $dedupSvc;


    public function __construct(
        LoggerInterface $logger,
        JobExecutorInterface $jobExecutor,
        PushDelayedResolver $pushDelayedResolver,
        JobLoaderInterface $jobLoader,
        SqsClient $sqsClient,
        MessageDeduplicationInterface $dedupSvc
    ) {
        $this->logger = $logger;
        $this->pushDelayedResolver = $pushDelayedResolver;
        $this->jobExecutor = $jobExecutor;
        $this->jobLoader = $jobLoader;
        $this->sqsClient = $sqsClient;
        $this->dedupSvc = $dedupSvc;
    }


    public function __invoke(SqsMessage $message): void
    {
        try {
            if ($this->dedupSvc->isDuplicate($message)) {
                $this->logger->warning('Duplicate message detected: ' . $message->getBody());

                $this->sqsClient->deleteMessage([
                    'QueueUrl' => $message->getQueueUrl(),
                    'ReceiptHandle' => $message->getReceiptHandle(),
                ]);

                return;
            }

            $this->executeJob($message);

            $this->sqsClient->deleteMessage([
                'QueueUrl' => $message->getQueueUrl(),
                'ReceiptHandle' => $message->getReceiptHandle(),
            ]);
        } catch (ConsumerFailedExceptionInterface $exception) {
            // do not delete message.
            // Afer visibility timeout message should be visible to other consumers.
            $this->logger->error(
                'Consumer failed, job requeued: ' . $exception->getMessage(),
                ['exception' => $exception]
            );

            throw $exception;
        } catch (UnresolvableProcessFailExceptionInterface $exception) {
            $this->logger->warning(
                'Job removed from queue: ' . $exception->getMessage(),
                ['exception' => $exception]
            );

            $this->sqsClient->deleteMessage([
                'QueueUrl' => $message->getQueueUrl(),
                'ReceiptHandle' => $message->getReceiptHandle(),
            ]);
        }
    }


    private function executeJob(SqsMessage $message): void
    {
        try {
            $job = $this->jobLoader->loadJob($message->getBody());

            $this->jobExecutor->execute($job);
        } catch (DelayableProcessFailExceptionInterface $exception) {
            CommonUtils::logDelayableProcessFailException($exception, $this->logger);

            $this->pushDelayedResolver->resolve($exception->getJob(), $exception);
        }
    }
}
