<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Jobs\Execution\ConsumerFailedExceptionInterface;
use BE\QueueManagement\Jobs\Execution\DelayableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Execution\JobExecutorInterface;
use BE\QueueManagement\Jobs\Execution\JobLoaderInterface;
use BE\QueueManagement\Jobs\Execution\UnresolvableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Execution\WarningOnlyExceptionInterface;
use BE\QueueManagement\Jobs\FailResolving\PushDelayedResolver;
use Aws\Sqs\SqsClient;
use Psr\Log\LoggerInterface;
use function sprintf;

class SqsConsumer implements SqsConsumerInterface 
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var PushDelayedResolver
     */
    protected $pushDelayedResolver;

    /**
     * @var JobExecutorInterface
     */
    protected $jobExecutor;

    /**
     * @var JobLoaderInterface
     */
    protected $jobLoader;

    /**
     * @var SqsClient
     */
    protected $sqsClient;    
    
    public function __construct(
        LoggerInterface $logger,
        JobExecutorInterface $jobExecutor,
        PushDelayedResolver $pushDelayedResolver,
        JobLoaderInterface $jobLoader,
        SqsClient $sqsClient
    ) {
        $this->logger = $logger;
        $this->pushDelayedResolver = $pushDelayedResolver;
        $this->jobExecutor = $jobExecutor;
        $this->jobLoader = $jobLoader;
        $this->sqsClient = $sqsClient;
    }
    

    public function __invoke(SqsMessage $message): void 
    {
        try {
            $this->executeJob($message);

            $this->sqsClient->deleteMessage([
                'QueueUrl' => $message->getQueueUrl(),
                'ReceiptHandle' => $message->getReceiptHandle()
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
                'ReceiptHandle' => $message->getReceiptHandle()
            ]);
        }
    }

    private function executeJob(SqsMessage $message): void
    {
        try {
            $job = $this->jobLoader->loadJob($message->getBody());

            $this->jobExecutor->execute($job);
        } catch (DelayableProcessFailExceptionInterface $exception) {
            $this->logDelayableProcessFailException($exception);

            $this->pushDelayedResolver->resolve($exception->getJob(), $exception);
        }
    }


    // TODO: dedub, this is same code as in RabbitMQConsumer, move to generic/parent class
    private function logDelayableProcessFailException(DelayableProcessFailExceptionInterface $exception): void
    {
        $message = sprintf(
            'Job execution failed [attempts: %s], reason: %s',
            $exception->getJob()->getAttempts(),
            $exception->getMessage()
        );
        $context = [
            'exception' => $exception,
            'previousException' => $exception->getPrevious(),
        ];

        if ($exception instanceof WarningOnlyExceptionInterface) {
            $this->logger->warning($message, $context);

            return;
        }

        $this->logger->error($message, $context);
    }    
}