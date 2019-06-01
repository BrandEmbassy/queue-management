<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Execution;

use BE\QueueManagement\Jobs\JobInterface;
use Throwable;

interface DelayableProcessFailExceptionInterface extends Throwable
{
    public function getJob(): JobInterface;
}
