<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Jobs\Execution\ConsumerFailedExceptionInterface;
use BE\QueueManagement\Jobs\Execution\DelayableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Execution\JobExecutorInterface;
use BE\QueueManagement\Jobs\Execution\JobLoaderInterface;
use BE\QueueManagement\Jobs\Execution\UnresolvableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Execution\WarningOnlyExceptionInterface;
use BE\QueueManagement\Jobs\FailResolving\PushDelayedResolver;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use function sprintf;

class RabbitMQConsumer implements RabbitMQConsumerInterface
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


    public function __construct(
        LoggerInterface $logger,
        JobExecutorInterface $jobExecutor,
        PushDelayedResolver $pushDelayedResolver,
        JobLoaderInterface $jobLoader
    ) {
        $this->logger = $logger;
        $this->pushDelayedResolver = $pushDelayedResolver;
        $this->jobExecutor = $jobExecutor;
        $this->jobLoader = $jobLoader;
    }


    public function __invoke(AMQPMessage $message): void
    {
        /** @var AMQPChannel $channel */
        $channel = $message->delivery_info['channel'];

        try {
            $this->executeJob($message);

            $channel->basic_ack($message->delivery_info['delivery_tag']);
        } catch (ConsumerFailedExceptionInterface $exception) {
            $channel->basic_reject($message->delivery_info['delivery_tag'], true);

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

            $channel->basic_nack($message->delivery_info['delivery_tag']);
        }
    }


    private function executeJob(AMQPMessage $message): void
    {
        try {
            $job = $this->jobLoader->loadJob($message->getBody());

            $this->jobExecutor->execute($job);
        } catch (DelayableProcessFailExceptionInterface $exception) {
            $this->logDelayableProcessFailException($exception);

            $this->pushDelayedResolver->resolve($exception->getJob(), $exception);
        }
    }


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
