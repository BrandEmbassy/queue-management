<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Execution;

use RuntimeException;

class MaximumAttemptsExceededException extends RuntimeException implements UnresolvableProcessFailExceptionInterface
{
}
