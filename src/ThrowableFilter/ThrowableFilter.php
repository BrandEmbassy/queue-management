<?php declare(strict_types = 1);

namespace BE\QueueManagement\ThrowableFilter;

use Throwable;

interface ThrowableFilter
{
    public function isApplicable(Throwable $throwable): bool;
}
