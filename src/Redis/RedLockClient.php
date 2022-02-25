<?php declare(strict_types = 1);

namespace BE\QueueManagement\Redis;

use RedLock\RedLock;

final class RedLockClient
{

    /**
     * 
     */
    private array $servers;

    public function __construct(array $servers)
    {
        $this->servers = $servers;
    }


}