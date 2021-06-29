<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue;

use BE\QueueManagement\Jobs\Execution\ConsumerFailedExceptionInterface;
use BE\QueueManagement\Jobs\Execution\UnresolvableProcessFailExceptionInterface;

interface MessageConsumerInterface
{
    /**
     * @throws ConsumerFailedExceptionInterface
     * @throws UnresolvableProcessFailExceptionInterface
     */
    public function consume(string $message): void;
}
