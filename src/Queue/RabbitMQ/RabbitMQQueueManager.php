<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Queue\QueueManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;
use function count;
use function sprintf;

class RabbitMQQueueManager implements QueueManagerInterface
{
    public const PREFETCH_COUNT = 'prefetchCount';
    public const NO_ACK = 'noAck';
    private const QUEUES_EXCHANGE_SUFFIX = '.sync';

    /**
     * @var ConnectionFactoryInterface
     */
    private $connectionFactory;

    /**
     * @var Collection<int, string>|string[]
     */
    private $declaredQueues;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var AMQPChannel|null
     */
    private $channel;

    /**
     * @var AMQPStreamConnection|null
     */
    private $connection;


    public function __construct(ConnectionFactoryInterface $connectionFactory, LoggerInterface $logger)
    {
        $this->connectionFactory = $connectionFactory;
        $this->logger = $logger;
        $this->declaredQueues = new ArrayCollection();
    }


    /**
     * @param mixed[] $parameters
     */
    public function consumeMessages(callable $consumer, string $queueName, array $parameters = []): void
    {
        $prefetchCount = $parameters[self::PREFETCH_COUNT] ?? 1;
        $noAck = $parameters[self::NO_ACK] ?? false;

        $this->declareQueueIfNotDeclared($queueName);

        $this->getChannel()->basic_qos(0, $prefetchCount, false);
        $this->getChannel()->basic_consume($queueName, '', false, $noAck, false, false, $consumer);

        while (count($this->getChannel()->callbacks) > 0) {
            $this->getChannel()->wait();
        }

        $this->closeConnection();
    }


    public function push(JobInterface $job): void
    {
        $queueName = $job->getJobDefinition()->getQueueName();

        $this->declareQueueIfNotDeclared($queueName);

        $this->publishMessage($job->toJson(), $queueName);

        $this->logger->info(
            sprintf(
                'Job (%s) [%s] pushed into %s queue',
                $job->getName(),
                $job->getUuid(),
                $queueName
            )
        );
    }


    public function pushDelayed(JobInterface $job, int $delayInSeconds): void
    {
        $this->pushDelayedWithMilliseconds($job, $delayInSeconds * 1000);
    }


    public function pushDelayedWithMilliseconds(JobInterface $job, int $delayInMilliseconds): void
    {
        $queueName = $job->getJobDefinition()->getQueueName();

        $this->declareQueueIfNotDeclared($queueName);

        $parameters = [
            'application_headers' => new AMQPTable(
                ['x-delay' => $delayInMilliseconds]
            ),
        ];

        $this->publishMessage($job->toJson(), $queueName, $parameters);
    }


    /**
     * @param mixed[] $arguments
     */
    protected function declareQueueIfNotDeclared(string $queueName, array $arguments = []): void
    {
        if ($this->declaredQueues->contains($queueName)) {
            return;
        }

        $this->declareQueue($queueName, $arguments);
        $this->declaredQueues->add($queueName);
    }


    /**
     * @param mixed[] $arguments
     */
    protected function declareQueue(string $queueName, array $arguments = []): void
    {
        $syncExchange = $this->getQueueExchangeName($queueName);

        $this->getChannel()->queue_declare($queueName, false, true, false, false, false, $arguments);

        $this->declareExchange($syncExchange, 'x-delayed-message', ['x-delayed-type' => ['S', 'direct']]);

        $this->getChannel()->queue_bind($queueName, $syncExchange);
        $this->declaredQueues->add($queueName);
    }


    /**
     * @param mixed[] $arguments
     */
    private function declareExchange(string $exchangeName, string $type, array $arguments = []): void
    {
        $this->getChannel()->exchange_declare(
            $exchangeName,
            $type,
            false,
            true,
            false,
            false,
            false,
            $arguments
        );
    }


    protected function getQueueExchangeName(string $queueName): string
    {
        return $queueName . self::QUEUES_EXCHANGE_SUFFIX;
    }


    /**
     * @param mixed[] $properties
     */
    private function publishMessage(string $message, string $queueName, array $properties = []): void
    {
        $properties['delivery_mode'] = AMQPMessage::DELIVERY_MODE_PERSISTENT;

        $amqpMessage = new AMQPMessage($message, $properties);

        $this->getChannel()->basic_publish($amqpMessage, $this->getQueueExchangeName($queueName));
    }


    public function closeConnection(): void
    {
        $this->getChannel()->close();
        $this->getConnection()->close();
    }


    private function getConnection(): AMQPStreamConnection
    {
        if ($this->connection === null) {
            $this->connection = $this->connectionFactory->create();
        }

        return $this->connection;
    }


    protected function getChannel(): AMQPChannel
    {
        if ($this->channel === null) {
            $this->channel = $this->getConnection()->channel();
        }

        return $this->channel;
    }


    public function checkConnection(): bool
    {
        return $this->getConnection()->isConnected();
    }
}
