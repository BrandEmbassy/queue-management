<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS\SqsScheduler;

use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\JobValidationException;
use BrandEmbassy\DateTime\DateTimeFormatter;
use BrandEmbassy\SqsSchedulerClient\SqsSchedulerClient;
use GuzzleHttp\Exception\GuzzleException;
use Ramsey\Uuid\UuidFactory;
use function assert;

class SqsScheduler implements DelayedJobSchedulerInterface
{
    private const SQS_SCHEDULER = 'sqs-scheduler';

    private const REMAINING_RETRIES_COUNT = 3;


    public function __construct(
        private readonly SqsSchedulerClient $sqsSchedulerClient,
        private readonly UuidFactory $uuidFactory,
    ) {
    }


    public function getSchedulerName(): string
    {
        return self::SQS_SCHEDULER;
    }


    /**
     * @throws GuzzleException
     */
    public function scheduleJob(JobInterface $job, string $prefixedQueueName): string
    {
        $eventId = $this->uuidFactory->uuid4();

        $brandId = $this->getBrandId($job);

        $executionPlannedAt = $job->getExecutionPlannedAt();
        assert($executionPlannedAt !== null, 'job.executionPlannedAt at must be set');

        $data = [
            SqsSchedulerFields::EVENT_ID => $eventId->toString(),
            SqsSchedulerFields::JOB_ID => $job->getUuid(),
            SqsSchedulerFields::BRAND_ID => $brandId,
            SqsSchedulerFields::CXONE_USER_ID => null,
            SqsSchedulerFields::USER_ID => null,
            SqsSchedulerFields::DESTINATION_QUEUE_NAME => $prefixedQueueName,
            SqsSchedulerFields::DELIVERY_SCHEDULED_AT => DateTimeFormatter::format($executionPlannedAt),
            SqsSchedulerFields::REMAINING_RETRIES => self::REMAINING_RETRIES_COUNT,
            SqsSchedulerFields::DATA => $job->toArray(),
        ];

        $this->sqsSchedulerClient->scheduleMessage($data);

        return $eventId->toString();
    }


    private function getBrandId(JobInterface $job): ?string
    {
        try {
            return $job->getParameter(SqsSchedulerFields::BRAND_ID);
        } catch (JobValidationException $exception) {
            return null;
        }
    }
}
