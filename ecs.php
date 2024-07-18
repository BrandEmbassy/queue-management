<?php declare(strict_types = 1);

use PHP_CodeSniffer\Standards\Squiz\Sniffs\PHP\CommentedOutCodeSniff;
use Symplify\EasyCodingStandard\Config\ECSConfig;

$defaultEcsConfigurationSetup = require 'vendor/brandembassy/coding-standard/default-ecs.php';

return static function (ECSConfig $ecsConfig) use ($defaultEcsConfigurationSetup): void {
    $defaultSkipList = $defaultEcsConfigurationSetup($ecsConfig, __DIR__);

    $ecsConfig->paths([
        'src',
        'tests',
        'ecs.php',
    ]);

    $skipList = [
        CommentedOutCodeSniff::class . '.Found' => [
            'tests/Queue/AWSSQS/SqsClientFactoryTest.php',
        ],
    ];

    $ecsConfig->skip(array_merge($defaultSkipList, $skipList));
};
