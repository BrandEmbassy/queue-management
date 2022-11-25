<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Logging\LoggerContextField;
use BrandEmbassy\DateTime\DateTimeImmutableFactory;
use Psr\Log\LoggerInterface;
use Throwable;
use Tracy\Debugger;
use function round;

class JobExecutor implements JobExecutorInterface
{
    protected LoggerInterface $logger;

    protected DateTimeImmutableFactory $dateTimeImmutableFactory;


    public function __construct(LoggerInterface $logger, DateTimeImmutableFactory $dateTimeImmutableFactory)
    {
        $this->logger = $logger;
        $this->dateTimeImmutableFactory = $dateTimeImmutableFactory;
    }


    public function execute(JobInterface $job): void
    {
        try {
            Debugger::timer('job-execution');
            $jobDefinition = $job->getJobDefinition();
            $processor = $jobDefinition->getJobProcessor();

            $startedAt = $this->dateTimeImmutableFactory->getNow();

            $job->executionStarted($startedAt);

            $this->logger->info(
                'Job execution start',
                [
                    LoggerContextField::JOB_QUEUE_NAME => $jobDefinition->getQueueName(),
                    LoggerContextField::JOB_NAME => $job->getName(),
                    LoggerContextField::JOB_UUID => $job->getUuid(),
                ],
            );

            $processor->process($job);

            $this->logger->info(
                'Job execution success',
                [
                    LoggerContextField::JOB_EXECUTION_TIME => round(Debugger::timer('job-execution') * 1000, 5),
                    LoggerContextField::JOB_QUEUE_NAME => $jobDefinition->getQueueName(),
                    LoggerContextField::JOB_NAME => $job->getName(),
                    LoggerContextField::JOB_UUID => $job->getUuid(),
                ],
            );
        } catch (ConsumerFailedExceptionInterface | UnresolvableProcessFailExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new UnableToProcessLoadedJobException(
                $job,
                $exception->getMessage(),
                (int)$exception->getCode(),
                $exception,
            );
        }
    }
}
