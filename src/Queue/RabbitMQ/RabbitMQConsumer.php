<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Jobs\Execution\ConsumerFailedExceptionInterface;
use BE\QueueManagement\Jobs\Execution\UnresolvableProcessFailExceptionInterface;
use BE\QueueManagement\Queue\MessageConsumer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use function assert;

final class RabbitMQConsumer implements RabbitMQConsumerInterface
{
    /**
     * @var MessageConsumer
     */
    private $messageConsumer;

    /**
     * @var LoggerInterface
     */
    private $logger;


    public function __construct(
        MessageConsumer $messageConsumer,
        LoggerInterface $logger
    ) {
        $this->messageConsumer = $messageConsumer;
        $this->logger = $logger;
    }


    public function __invoke(AMQPMessage $message): void
    {
        $channel = $message->getChannel();
        assert($channel instanceof AMQPChannel);

        try {
            $this->messageConsumer->consume($message->getBody());

            $channel->basic_ack($message->getDeliveryTag());
        } catch (ConsumerFailedExceptionInterface $exception) {
            $channel->basic_reject($message->getDeliveryTag(), true);

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

            $channel->basic_nack($message->getDeliveryTag());
        }
    }
}
