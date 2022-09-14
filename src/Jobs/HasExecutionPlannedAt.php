<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use DateTimeImmutable;

trait HasExecutionPlannedAt
{
    private ?DateTimeImmutable $executionPlannedAt = null;


    public function getExecutionPlannedAt(): ?DateTimeImmutable
    {
        return $this->executionPlannedAt;
    }


    public function setExecutionPlannedAt(?DateTimeImmutable $executionPlannedAt): void
    {
        $this->executionPlannedAt = $executionPlannedAt;
    }
}
