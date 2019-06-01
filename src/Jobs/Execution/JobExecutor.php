<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\JobInterface;
use BrandEmbassy\DateTime\DateTimeImmutableFactory;
use Psr\Log\LoggerInterface;
use Throwable;

class JobExecutor implements JobExecutorInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var DateTimeImmutableFactory
     */
    protected $dateTimeImmutableFactory;


    public function __construct(LoggerInterface $logger, DateTimeImmutableFactory $dateTimeImmutableFactory)
    {
        $this->logger = $logger;
        $this->dateTimeImmutableFactory = $dateTimeImmutableFactory;
    }


    public function execute(JobInterface $job): void
    {
        try {
            $processor = $job->getJobDefinition()->getJobProcessor();

            $startedAt = $this->dateTimeImmutableFactory->getNow();

            $job->executionStarted($startedAt);

            $this->logger->info('Job execution start');

            $processor->process($job);

            $executedAt = $this->dateTimeImmutableFactory->getNow();

            $diff = $executedAt->getTimestamp() - $startedAt->getTimestamp();

            $this->logger->info(
                'Job execution success [' . $diff . ' sec]',
                ['executionTime' => $diff]
            );
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
