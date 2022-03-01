<?php declare(strict_types=1);

namespace BE\QueueExample\Common;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;


class LoggerFactory
{
	public function create(string $loggerName): LoggerInterface
	{
		$log = new Logger($loggerName);
		$log->pushHandler(new StreamHandler('/tmp/your.log', Logger::INFO));
		return $log;
	}
}

