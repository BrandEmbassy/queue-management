<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue;

/**
 * @final
 */
class QueueWorkerState
{
    private bool $shouldStop = false;


    public function stop(): void
    {
        $this->shouldStop = true;
    }


    public function shouldStop(): bool
    {
        return $this->shouldStop;
    }
}
