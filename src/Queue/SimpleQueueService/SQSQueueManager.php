<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\SimpleQueueService;

use Aws\Sqs\SqsClient;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Queue\QueueManagerInterface;
use Ramsey\Uuid\Uuid;

final class SQSQueueManager implements QueueManagerInterface
{
    /**
     * @var SqsClient
     */
    private $sqsClient;


    public function __construct(SqsClient $sqsClient)
    {
        $this->sqsClient = $sqsClient;
    }


    public function consumeMessages(callable $consumer, string $queueName, array $parameters = []): void
    {
        $queueUrl = 'https://sqs.eu-west-1.amazonaws.com/563770389081/' . $queueName;

        while (true) {
            $receiveResult = $this->sqsClient->receiveMessage(
                [
                    'AttributeNames' => ['SentTimestamp'],
                    'MaxNumberOfMessages' => $parameters['MaxNumberOfMessages'] ?? 1,
                    'MessageAttributeNames' => ['All'],
                    'QueueUrl' => $queueUrl,
                    'WaitTimeSeconds' => $parameters['WaitTimeSeconds'] ?? 3,
                ]
            );

            $resultMessage = $receiveResult->get('Messages')[0] ?? null;

            if ($resultMessage === null) {
                continue;
            }

            $consumer($resultMessage);

            $this->sqsClient->deleteMessage(
                [
                    'QueueUrl' => $queueUrl, // REQUIRED
                    'ReceiptHandle' => $resultMessage['ReceiptHandle'] // REQUIRED
                ]
            );
        }
    }


    public function push(JobInterface $job): void
    {
        $queueUrl = 'https://sqs.eu-west-1.amazonaws.com/563770389081/' . $job->getJobDefinition()->getQueueName();

        $params = [
            'MessageBody' => $job->toJson(),
            'QueueUrl' => $queueUrl,
            'MessageAttributes' => [
                'JobUuid' => [
                    'DataType' => 'String',
                    'StringValue' => $job->getUuid(),
                ],
            ],
            'MessageGroupId' => Uuid::uuid4()->toString(),
            'MessageDeduplicationId' => Uuid::uuid4()->toString(),
        ];

        $this->sqsClient->sendMessage($params);
    }


    public function pushDelayed(JobInterface $job, int $delayInSeconds): void
    {
        $this->push($job);
    }


    public function pushDelayedWithMilliseconds(JobInterface $job, int $delayInMilliseconds): void
    {
        $this->push($job);
    }


    public function checkConnection(): bool
    {
        return true;
    }
}
