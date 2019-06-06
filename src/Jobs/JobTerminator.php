<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use BE\AdapterSdk\Queue\Jobs\JobInterface;
use Psr\Log\LoggerInterface;
use function sprintf;

class JobTerminator
{
    /**
     * @var int
     */
    private $minimumAttempts;

    /**
     * @var JobUuidBlacklistInterface
     */
    private $jobUuidBlacklist;

    /**
     * @var LoggerInterface
     */
    private $logger;


    public function __construct(
        int $minimumAttempts,
        JobUuidBlacklistInterface $jobUuidBlacklist,
        LoggerInterface $logger
    ) {
        $this->minimumAttempts = $minimumAttempts;
        $this->jobUuidBlacklist = $jobUuidBlacklist;
        $this->logger = $logger;
    }


    public function shouldBeTerminated(string $jobUuid, int $attempts): bool
    {
        if ($attempts < $this->minimumAttempts) {
            return false;
        }

        return $this->jobUuidBlacklist->contains($jobUuid);
    }


    public function terminate(string $jobUuid): void
    {
        $this->jobUuidBlacklist->remove($jobUuid);
        $this->logger->warning(sprintf('Job %s was terminated', $jobUuid));
    }
}
