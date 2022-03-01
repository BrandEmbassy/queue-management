<?php declare(strict_types = 1);

require __DIR__ . '/vendor/autoload.php';

use BE\QueueExample\Sqs\JobPusher;

// create DI container
$autoRebuild = true;
$loader = new Nette\DI\ContainerLoader(__DIR__ . '/temp', $autoRebuild);
$class = $loader->load(function($compiler) {
	$compiler->loadConfig(__DIR__ . '/config-sqs.neon');
});
$container = new $class;

// let the container create an instance of the UserController
$pusher = $container->getByType(JobPusher::class);

$pusher->push('uuid-123');

echo "sqs job pusher done!";