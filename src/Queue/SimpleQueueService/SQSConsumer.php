<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\SimpleQueueService;

use BE\QueueManagement\Jobs\Execution\ConsumerFailedExceptionInterface;
use BE\QueueManagement\Jobs\Execution\UnresolvableProcessFailExceptionInterface;
use BE\QueueManagement\Queue\MessageConsumer;
use Psr\Log\LoggerInterface;

final class SQSConsumer
{
    /**
     * @var MessageConsumer
     */
    private $messageConsumer;

    /**
     * @var LoggerInterface
     */
    private $logger;


    public function __construct(MessageConsumer $messageConsumer, LoggerInterface $logger)
    {
        $this->messageConsumer = $messageConsumer;
        $this->logger = $logger;
    }


    public function __invoke(array $messageData): void
    {
        try {
            $this->messageConsumer->consume($messageData['Body']);
        } catch (ConsumerFailedExceptionInterface $exception) {
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
        }
    }
}
