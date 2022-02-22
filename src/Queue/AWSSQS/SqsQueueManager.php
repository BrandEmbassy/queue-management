<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Queue\QueueManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use ErrorException;
use Psr\Log\LoggerInterface;
use Throwable;
use function count;
use function sprintf;

class SqsQueueManager implements QueueManagerInterface
{
    /**
     * The maximum number of messages to return. 
     * Amazon SQS never returns more messages than this value (however, fewer messages might be returned). 
     * Valid values: 1 to 10. Default: 1.
     */
    public const MAX_NUMBER_OF_MESSAGES = 'MaxNumberOfMessages';

    public const WAIT_TIME_SECONDS = 'WaitTimeSeconds';
    
    private const MAX_RECONNECTS = 15;

    /**
     * @var SqsClientFactoryInterface
     */
    private $sqsClientFactory;

    /**
     * @var Collection<int, string>|string[]
     */
    private $declaredQueues;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @var int
     */
    private $reconnectCounter;


    public function __construct(SqsClientFactoryInterface $sqsClientFactory, LoggerInterface $logger)
    {
        $this->sqsClientFactory = $sqsClientFactory;
        $this->logger = $logger;
        $this->declaredQueues = new ArrayCollection();
        $this->reconnectCounter = 0;
    }



    /**
     * @param mixed[] $parameters
     *
     * @throws SqsClientException
     */
    public function consumeMessages(callable $consumer, string $queueName, array $parameters = []): void
    {
        $maxNumberOfMessages = (int)($parameters[self::MAX_NUMBER_OF_MESSAGES] ?? 1);
        $waitTimeSeconds = (int)($parameters[self::WAIT_TIME_SECONDS] ?? 10);
        
        $this->declareQueueIfNotDeclared($queueName);

        $sqsClient = $this->sqsClientFactory->create();

        while(true) {
            try {
                $result = $client->receiveMessage(array(
                    'AttributeNames' => ['All'],
                    'MaxNumberOfMessages' => maxNumberOfMessages,
                    'MessageAttributeNames' => ['All'],
                    'QueueUrl' => $queueName,
                    'WaitTimeSeconds' => waitTimeSeconds,
                ));

                if (!empty($result->get('Messages'))) {
                    $sqsMessages = SqsMessageFactory::fromAwsResult($result, $queueName);
                    foreach ($sqsMessages as $sqsMessage) {
                        $consumer($sqsMessage);
                    }
                }

            } catch(RuntimeException $exception) {
                $this->logger->warning(
                    'SQS receiveMessage runtime exception: ' . $exception->getMessage(),
                    ['exception' => $exception]
                );

                $this->reconnect($exception, $queueName);
            }
        }

        $this->clearConnection($queueName);
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
        // TBD: https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-sqs-2012-11-05.html#createqueue
        $this->declaredQueues->add($queueName);
    }




    /**
     * @param mixed[] $properties
     *
     * @throws ConnectionException
     */
    private function publishMessage(string $message, string $queueName, array $properties = []): void
    {
        $properties['delivery_mode'] = AMQPMessage::DELIVERY_MODE_PERSISTENT;

        $amqpMessage = new AMQPMessage($message, $properties);

        try {
            $this->getChannel()->basic_publish($amqpMessage, $this->getQueueExchangeName($queueName));
        } catch (AMQPRuntimeException $exception) {
            $this->reconnect($exception, $queueName);

            $this->getChannel()->basic_publish($amqpMessage, $this->getQueueExchangeName($queueName));
        }
    }


    public function clearConnection(string $queueName): void
    {
        $this->closeConnection();
        $this->closeChannel();
        $this->declaredQueues->removeElement($queueName);
    }


    public function closeConnection(): void
    {
        if ($this->connection === null) {
            return;
        }

        try {
            $this->connection->close();
        } catch (ErrorException $exception) {
            $this->logger->warning('Connection was already closed: ' . $exception->getMessage());
        }
    }


    /**
     * @throws ConnectionException
     */
    private function reconnect(Throwable $exception, string $queueName): void
    {
        if ($this->reconnectCounter >= self::MAX_RECONNECTS) {
            throw ConnectionException::createMaximumReconnectLimitReached(self::MAX_RECONNECTS);
        }

        $this->clearConnection($queueName);
        $this->connection = $this->createConnection();
        $this->channel = $this->createChannel();
        $this->reconnectCounter++;

        $this->logger->warning(
            'Reconnecting: ' . $exception->getMessage(),
            [
                'queueName' => $queueName,
                'exception' => $exception->getTraceAsString(),
            ]
        );
    }


    private function getConnection(): AMQPStreamConnection
    {
        if ($this->connection === null) {
            $this->connection = $this->createConnection();
        }

        return $this->connection;
    }


    private function createConnection(): AMQPStreamConnection
    {
        return $this->connectionFactory->create();
    }


    public function checkConnection(): bool
    {
        return $this->getConnection()->isConnected();
    }
}
