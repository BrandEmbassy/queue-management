<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS\SqsScheduler;

use BE\QueueManagement\Jobs\JobInterface;
use BrandEmbassy\SqsSchedulerClient\SqsSchedulerClient;
use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidFactory;
use Tests\BE\QueueManagement\Jobs\ExampleJob;
use Tests\BE\QueueManagement\Jobs\Execution\ExampleJobProcessor;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;

class SqsSchedulerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const PREFIXED_QUEUE_NAME = 'test-queue';

    private const DATE_TIME = '2023-10-01T12:00:00Z';

    private SqsSchedulerClient&MockInterface $sqsSchedulerClientMock;

    private UuidFactory&MockInterface $uuidFactoryMock;


    protected function setUp(): void
    {
        parent::setUp();
        $this->sqsSchedulerClientMock = Mockery::mock(SqsSchedulerClient::class);
        $this->uuidFactoryMock = Mockery::mock(UuidFactory::class);
    }


    public function testJobIsScheduled(): void
    {
        $this->uuidFactoryMock->expects('uuid4')
            ->andReturn(Uuid::fromString('123e4567-e89b-12d3-a456-426614174000'));

        $this->sqsSchedulerClientMock->expects('scheduleMessage')
            ->with(
                [
                    'eventId' => '123e4567-e89b-12d3-a456-426614174000',
                    'jobId' => 'some-job-uuid',
                    'brandId' => null,
                    'cxoneUserId' => null,
                    'userId' => null,
                    'destinationQueueName' => 'test-queue',
                    'deliveryScheduledAt' => '2023-10-01T12:00:00+00:00',
                    'remainingRetries' => 3,
                    'data' => [
                        'jobUuid' => 'some-job-uuid',
                        'jobName' => 'exampleJob',
                        'attempts' => 1,
                        'createdAt' => '2018-08-01T10:15:47+01:00',
                        'jobParameters' => [
                            'foo' => 'bar',
                        ],
                        'executionPlannedAt' => '2023-10-01T12:00:00+00:00',
                    ],
                ],
            );

        $sqsScheduler = new SqsScheduler(
            $this->sqsSchedulerClientMock,
            $this->uuidFactoryMock,
        );

        $eventId = $sqsScheduler->scheduleJob(
            $this->createExampleJob(),
            self::PREFIXED_QUEUE_NAME,
        );

        Assert::assertSame('123e4567-e89b-12d3-a456-426614174000', $eventId);
    }


    private function createExampleJob(): JobInterface
    {
        $exampleJobDefinition = ExampleJobDefinition::create()
            ->withJobProcessor(new ExampleJobProcessor());

        return new ExampleJob($exampleJobDefinition, 'bar', [], new DateTimeImmutable(self::DATE_TIME));
    }
}
