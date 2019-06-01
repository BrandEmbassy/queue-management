<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\JobInterface;
use BrandEmbassy\DateTime\DateTimeImmutableFactory;
use Psr\Log\LoggerInterface;
use Throwable;

class JobExecutor
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DateTimeImmutableFactory
     */
    private $dateTimeImmutableFactory;


    public function __construct(LoggerInterface $logger, DateTimeImmutableFactory $dateTimeImmutableFactory)
    {
        $this->logger = $logger;
        $this->dateTimeImmutableFactory = $dateTimeImmutableFactory;
    }


    public function execute(JobInterface $job): void
    {
        try {
            $processor = $job->getJobDefinition()->getJobProcessor();

            $job->executionStarted($this->dateTimeImmutableFactory->getNow());

            $this->logger->info('Job execution start');

            $processor->process($job);

            $this->logger->info('Job execution success');
        } catch (ConsumerFailedExceptionInterface | UnresolvableProcessFailExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new UnableToProcessLoadedJobException(
                $job,
                $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }
    }
}
