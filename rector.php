<?php declare(strict_types = 1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfigBuilder = RectorConfig::configure();
    $defaultRectorConfigurationSetup = require __DIR__ . '/vendor/brandembassy/coding-standard/default-rector.php';

    $defaultSkipList = $defaultRectorConfigurationSetup($rectorConfigBuilder);

    $rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');

    $rectorConfig->cacheClass(FileCacheStorage::class);
    $rectorConfig->cacheDirectory('./var/rector');

    $rectorConfig->sets([
        PHPUnitSetList::PHPUNIT_100
    ]);

    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $skipList = [];

    $rectorConfig->skip(
        array_merge(
            $defaultSkipList,
            $skipList
        )
    );
};
