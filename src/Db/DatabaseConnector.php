<?php declare(strict_types = 1);

namespace BE\QueueManagement\Db;

/**
 * @final
 */
class DatabaseConnector implements DatabaseConnectorInterface
{
    public function reconnect(): void
    {
        return;
    }
}
