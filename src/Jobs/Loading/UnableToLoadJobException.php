<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\Loading;

use BE\QueueManagement\Jobs\Execution\UnresolvableProcessFailExceptionInterface;
use Exception;

class UnableToLoadJobException extends Exception implements UnresolvableProcessFailExceptionInterface
{
}
