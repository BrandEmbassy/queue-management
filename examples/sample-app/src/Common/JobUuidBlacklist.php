<?php declare(strict_types = 1);

namespace BE\QueueExample\Common;

use BE\QueueManagement\Jobs\JobUuidBlacklistInterface;

// dummy implementation of job blacklist
class JobUuidBlacklist implements JobUuidBlacklistInterface {
   
    public function add(string $uuid): void {
        return;
    }

    public function remove(string $uuid): void {
        return;
    }

    public function contains(string $uuid): bool {
        return false;
    }

    public function all(): array {
        return [];
    }

}