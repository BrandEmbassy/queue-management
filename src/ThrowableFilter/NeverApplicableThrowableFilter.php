<?php declare(strict_types = 1);

namespace BE\QueueManagement\ThrowableFilter;

use Throwable;

/**
 * @final
 */
class NeverApplicableThrowableFilter implements ThrowableFilter
{
    public function isApplicable(Throwable $throwable): bool
    {
        return false;
    }
}
