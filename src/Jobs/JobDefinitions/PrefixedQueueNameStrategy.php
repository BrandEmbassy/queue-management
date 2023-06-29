<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

/**
 * @final
 */
class PrefixedQueueNameStrategy implements QueueNameStrategy
{
    private string $queueNamePrefix;


    public function __construct(string $queueNamePrefix)
    {
        $this->queueNamePrefix = $queueNamePrefix;
    }


    public static function createDefault(): self
    {
        return new self('');
    }


    public function getQueueName(string $queueName): string
    {
        return $this->queueNamePrefix . $queueName;
    }
}
