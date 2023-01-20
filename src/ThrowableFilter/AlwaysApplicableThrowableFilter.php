<?php declare(strict_types = 1);

namespace BE\QueueManagement\ThrowableFilter;

use Throwable;

/**
 * @final
 */
class AlwaysApplicableThrowableFilter implements ThrowableFilter
{
    public function isApplicable(Throwable $throwable): bool
    {
        return true;
    }
}
