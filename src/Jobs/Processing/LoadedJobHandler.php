<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Processing;

use BE\QueueManagement\Jobs\JobInterface;
use BrandEmbassy\DateTime\DateTimeImmutableFactory;
use Psr\Log\LoggerInterface;
use Throwable;

class LoadedJobHandler
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var JobProcessorsMapInterface
     */
    private $jobProcessorsMap;

    /**
     * @var DateTimeImmutableFactory
     */
    private $dateTimeImmutableFactory;

    /**
     * @var BeforeProcessHandlerInterface[]
     */
    private $beforeProcessHandlers;

    /**
     * @var AfterProcessHandlerInterface[]
     */
    private $afterProcessHandlers;


    /**
     * @param BeforeProcessHandlerInterface[] $beforeProcessHandlers
     * @param AfterProcessHandlerInterface[]  $afterProcessHandlers
     */
    public function __construct(
        array $beforeProcessHandlers,
        array $afterProcessHandlers,
        LoggerInterface $logger,
        JobProcessorsMapInterface $jobProcessorsMap,
        DateTimeImmutableFactory $dateTimeImmutableFactory
    ) {
        $this->logger = $logger;
        $this->jobProcessorsMap = $jobProcessorsMap;
        $this->dateTimeImmutableFactory = $dateTimeImmutableFactory;
        $this->beforeProcessHandlers = $beforeProcessHandlers;
        $this->afterProcessHandlers = $afterProcessHandlers;
    }


    public function handle(JobInterface $job): void
    {
        $this->beforeProcess($job);

        try {
            $processor = $this->jobProcessorsMap->getJobProcessor($job->getName());

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
        } finally {
            $this->afterProcess($job);
        }
    }


    private function beforeProcess(JobInterface $job): void
    {
        foreach ($this->beforeProcessHandlers as $beforeProcessHandler) {
            $beforeProcessHandler($job);
        }
    }


    private function afterProcess(JobInterface $job): void
    {
        foreach ($this->afterProcessHandlers as $afterProcessHandler) {
            $afterProcessHandler($job);
        }
    }
}
