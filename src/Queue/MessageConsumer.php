<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue;

use BE\QueueManagement\Jobs\Execution\ConsumerFailedExceptionInterface;
use BE\QueueManagement\Jobs\Execution\DelayableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Execution\JobExecutorInterface;
use BE\QueueManagement\Jobs\Execution\JobLoaderInterface;
use BE\QueueManagement\Jobs\Execution\UnresolvableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Execution\WarningOnlyExceptionInterface;
use BE\QueueManagement\Jobs\FailResolving\PushDelayedResolver;
use Psr\Log\LoggerInterface;
use function sprintf;

final class MessageConsumer implements MessageConsumerInterface
{
    /**
     * @var JobLoaderInterface
     */
    private $jobLoader;

    /**
     * @var JobExecutorInterface
     */
    private $jobExecutor;

    /**
     * @var PushDelayedResolver
     */
    private $pushDelayedResolver;

    /**
     * @var LoggerInterface
     */
    private $logger;


    public function __construct(
        JobLoaderInterface $jobLoader,
        JobExecutorInterface $jobExecutor,
        PushDelayedResolver $pushDelayedResolver,
        LoggerInterface $logger
    ) {
        $this->jobLoader = $jobLoader;
        $this->jobExecutor = $jobExecutor;
        $this->pushDelayedResolver = $pushDelayedResolver;
        $this->logger = $logger;
    }


    /**
     * @throws ConsumerFailedExceptionInterface
     * @throws UnresolvableProcessFailExceptionInterface
     */
    public function consume(string $message): void
    {
        try {
            $job = $this->jobLoader->loadJob($message);

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
