<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving;

use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Queue\QueueManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function sprintf;

class PushDelayedResolver
{
    /**
     * @var DelayRulesMapInterface
     */
    private $delayRulesMap;

    /**
     * @var QueueManagerInterface
     */
    private $queueManager;

    /**
     * @var LoggerInterface
     */
    private $logger;


    public function __construct(
        DelayRulesMapInterface $delayRulesMap,
        QueueManagerInterface $queueManager,
        LoggerInterface $logger
    ) {
        $this->delayRulesMap = $delayRulesMap;
        $this->queueManager = $queueManager;
        $this->logger = $logger;
    }


    public function resolve(JobInterface $job, Throwable $exception): void
    {
        $job->incrementAttempts();

        $delayRule = $this->delayRulesMap->getDelayRule($job->getName());

        $pushDelay = $delayRule->getDelay($job, $exception);

        $this->logger->error(
            sprintf(
                'Job execution failed [attempts: %s, next-ttl: %s], reason: %s',
                $job->getAttempts(),
                $pushDelay,
                $exception->getMessage()
            ),
            ['exception' => $exception]
        );

        $this->queueManager->pushDelayed($job, $pushDelay);
    }
}
