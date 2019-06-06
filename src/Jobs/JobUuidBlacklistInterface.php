<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

interface JobUuidBlacklistInterface
{
    public function add(string $uuid): void;


    public function remove(string $uuid): void;


    public function contains(string $uuid): bool;


    /**
     * @return string[]
     */
    public function all(): array;
}
