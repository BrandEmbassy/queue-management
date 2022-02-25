<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Queue\QueueManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Aws\Sqs\SqsClient;
use ErrorException;
use Aws\Exception\AwsException;
use Psr\Log\LoggerInterface;
use Throwable;
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

    public const DELAY_SECONDS = 'DelaySeconds';

    // SQS allows maximum message delay of 15 minutes
    public const MAX_DELAY_SECONDS = 15 * 60;

    private const MAX_RECONNECTS = 15;

    /**
     * @var SqsClientFactoryInterface
     */
    private $sqsClientFactory;


        /**
     * @var SqsClient
     */
    private $sqsClient;


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
        $this->sqsClient = $this->sqsClientFactory->create();
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
        $maxNumberOfMessages = (int)($parameters[self::MAX_NUMBER_OF_MESSAGES] ?? 10);
        $waitTimeSeconds = (int)($parameters[self::WAIT_TIME_SECONDS] ?? 10);
        
        $this->declareQueueIfNotDeclared($queueName);

        while(true) {
            try {
                $result = $this->sqsClient->receiveMessage(array(
                    'AttributeNames' => ['All'],
                    'MaxNumberOfMessages' => $maxNumberOfMessages,
                    'MessageAttributeNames' => ['All'],
                    'QueueUrl' => $queueName,
                    'WaitTimeSeconds' => $waitTimeSeconds,
                ));

                if (!empty($result->get('Messages'))) {
                    $sqsMessages = SqsMessageFactory::fromAwsResult($result, $queueName);
                    foreach ($sqsMessages as $sqsMessage) {
                        $consumer($sqsMessage);
                    }
                }
            } catch(AwsException $exception) {
                $this->logger->warning(
                    'AwsException: ' . $exception->getMessage(),
                    ['exception' => $exception]
                );

                $this->reconnect($exception, $queueName);
            }
        }
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


    public function pushDelayedWithMilliseconds(JobInterface $job, int $delayInMilliseconds): void
    {
        $this->pushDelayed($job, $delayInMilliseconds / 1000);
    }


    public function pushDelayed(JobInterface $job, int $delayInSeconds): void
    {
        $queueName = $job->getJobDefinition()->getQueueName();

        $this->declareQueueIfNotDeclared($queueName);

        $parameters = [
            self::DELAY_SECONDS => $delayInSeconds,
        ];

        $this->publishMessage($job->toJson(), $queueName, $parameters);
    }


    /**
     * @param mixed[] $arguments
     */
    protected function declareQueueIfNotDeclared(string $queueName, array $arguments = []): void
    {
        return; // TODO: this function will be probably removed completely
        if ($this->declaredQueues->contains($queueName)) {
            return;
        }

        $this->declareQueue($queueName, $arguments);
        $this->declaredQueues->add($queueName);
    }


    /**
     * Creates SQS queue. Tags not supported yet. No validation of passed arguments.
     * See: https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-sqs-2012-11-05.html#createqueue
     * @param mixed[] $arguments
     * @throws AwsException
     */
    protected function declareQueue(string $queueName, array $arguments = []): void
    {
        $this->sqsClient->createQueue([
            'Attributes' => $arguments,
            'QueueName' => $queueName
        ]);
        $this->declaredQueues->add($queueName);
    }




    /**
     * @param mixed[] $properties
     *
     * @throws AwsException
     * @throws SqsClientException
     */
    private function publishMessage(string $message, string $queueName, array $properties = []): void
    {
        $delaySeconds = (int)($properties[self::DELAY_SECONDS] ?? 0);

        if ($delaySeconds < 0 || $delaySeconds > self::MAX_DELAY_SECONDS) {
            throw SqsClientException::createMaximumReconnectLimitReached($delaySeconds);
        }
        
        $sqsMessage = [
            'DelaySeconds' => $delaySeconds,
            'MessageAttributes' => [
                'QueueUrl' => [
                    'DataType' => 'String',
                    // queueName might be handy here if we want to consume 
                    // from mutliple queues in parallel via promises. 
                    // Then we need queue in message directly so that we can delete it.
                    'StringValue' => $queueName 
                ]
            ],
            'MessageBody' => $message,
            'QueueUrl' => $queueName
        ];

        try {
            $this->sqsClient->sendMessage($sqsMessage);
        } catch (AwsException $exception) {
            $this->reconnect($exception, $queueName);
            $this->sqsClient->sendMessage($sqsMessage);
        }
    }

    /**
     * @throws SqsClientException
     */
    private function reconnect(Throwable $exception, string $queueName): void
    {
        if ($this->reconnectCounter >= self::MAX_RECONNECTS) {
            throw SqsClientException::createMaximumReconnectLimitReached(self::MAX_RECONNECTS);
        }

        $this->sqsClient = $this->sqsClientFactory->create();
        $this->reconnectCounter++;

        $this->logger->warning(
            'Reconnecting: ' . $exception->getMessage(),
            [
                'queueName' => $queueName,
                'exception' => $exception->getTraceAsString(),
            ]
        );
    }

    public function checkConnection(): bool
    {
        // No checkConn method in SqsClient. For now just providing fake response
        // in the future we might want to check somehow whether we still have connectivity.
        return true;
    }
}
