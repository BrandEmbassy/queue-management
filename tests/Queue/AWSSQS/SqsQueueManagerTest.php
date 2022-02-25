<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Queue\AWSSQS\SqsClientException;
use BE\QueueManagement\Queue\AWSSQS\SqsClientFactory;
use BE\QueueManagement\Queue\AWSSQS\SqsQueueManager;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\BE\QueueManagement\Jobs\ExampleJob;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;
use Aws\Sqs\SqsClient;

final class SqsQueueManagerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var SqsClientFactory&MockInterface
     */
    private $sqsClientFactoryMock;

    /**
     * @var LoggerInterface&MockInterface
     */
    private $loggerMock;

    /**
     * @var SqsClient&MockInterface
     */    
    private $sqsClientMock;


    public function setUp(): void
    {
        parent::setUp();
        $this->sqsClientFactoryMock = Mockery::mock(SqsClientFactory::class);
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->sqsClientMock = Mockery::mock(SqsClient::class);
    }

    public function testPush(): void
    {
        $this->expectSetUpConnection();

        $this->loggerMock->shouldReceive('info')
            ->with('Job (exampleJob) [some-job-uud] pushed into exampleJobQueue queue')
            ->once();

        $exampleJob = $this->createExampleJob();

        $this->sqsClientMock->shouldReceive('sendMessage')
            ->with(
                Mockery::on(
                    static function (array $message) use ($exampleJob): bool {
                        return $message['MessageBody'] === $exampleJob->toJson()
                            && $message[SqsMessage::ATTR_DELAYSECONDS] === 0
                            && $message[SqsMessage::ATTR_QUEUEURL] === ExampleJobDefinition::QUEUE_NAME
                            && $message[SqsMessage::ATTR_MESSAGEATTRIBUTES][SqsMessage::ATTR_QUEUEURL]['StringValue'] === ExampleJobDefinition::QUEUE_NAME;
                    }
                )
            )
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->push($exampleJob);
    }  
    
    public function testPushDelayed(): void
    {
        $this->expectSetUpConnection();

        $exampleJob = $this->createExampleJob();

        $this->sqsClientMock->shouldReceive('sendMessage')
            ->with(
                Mockery::on(
                    static function (array $message) use ($exampleJob): bool {
                        return $message['MessageBody'] === $exampleJob->toJson()
                            && $message[SqsMessage::ATTR_DELAYSECONDS] === 5
                            && $message[SqsMessage::ATTR_QUEUEURL] === ExampleJobDefinition::QUEUE_NAME
                            && $message[SqsMessage::ATTR_MESSAGEATTRIBUTES][SqsMessage::ATTR_QUEUEURL]['StringValue'] === ExampleJobDefinition::QUEUE_NAME;
                    }
                )
            )
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->pushDelayed($exampleJob, 5);
    }

    public function testPushDelayedWithMilliSeconds(): void
    {
        $this->expectSetUpConnection();

        $exampleJob = $this->createExampleJob();

        $this->sqsClientMock->shouldReceive('sendMessage')
            ->with(
                Mockery::on(
                    static function (array $message) use ($exampleJob): bool {
                        return $message['MessageBody'] === $exampleJob->toJson()
                            && $message[SqsMessage::ATTR_DELAYSECONDS] === 5
                            && $message[SqsMessage::ATTR_QUEUEURL] === ExampleJobDefinition::QUEUE_NAME
                            && $message[SqsMessage::ATTR_MESSAGEATTRIBUTES][SqsMessage::ATTR_QUEUEURL]['StringValue'] === ExampleJobDefinition::QUEUE_NAME;
                    }
                )
            )
            ->once();

        $queueManager = $this->createQueueManager();
        $queueManager->pushDelayedWithMilliseconds($exampleJob, 5000);
    }

    

    private function createExampleJob(): ExampleJob
    {
        return new ExampleJob();
    }


    private function createQueueManager(): SqsQueueManager
    {
        return new SqsQueueManager($this->sqsClientFactoryMock, $this->loggerMock);
    }

    private function expectSetUpConnection(int $connectionIsCreatedTimes = 1, int $channelIsCreatedTimes = 1): void
    {
        $this->sqsClientFactoryMock->shouldReceive('create')
            ->withNoArgs()
            ->times($connectionIsCreatedTimes)
            ->andReturn($this->sqsClientMock);
    }    
}