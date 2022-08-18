<?php declare(strict_types = 1);

namespace BE\QueueManagement\Db;

interface DatabaseConnectorInterface
{
    public function reconnect(): void;
}
