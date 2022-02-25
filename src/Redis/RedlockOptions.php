<?php declare(strict_types = 1);

namespace BE\QueueManagement\Redis;

/**
 * Options that needs to be specified when aquiring redlock
 * For details see: https://github.com/ronnylt/redlock-php
 */
final class RedlockOptions 
{
    private int $validityTime;

    private int $numberOfRetries;

    private int $retryDelay;


    public function __construct(
        int $validityTime,
        int $numberOfRetries = 3,
        int $retryDelay = 200 // 200ms
    ) {
        $this->validityTime = $validityTime;
        $this->numberOfRetries = $numberOfRetries;
        $this->retryDelay = $retryDelay;
    }

    public function getValidityTime(): int {
        return $this->validityTime;
    }

    public function getNumberOfRetries(): int {
        return $this->numberOfRetries;
    }

    public function getRetryDelay(): int {
        return $this->retryDelay;
    }
}