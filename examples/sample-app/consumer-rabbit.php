<?php declare(strict_types = 1);

require __DIR__ . '/vendor/autoload.php';

use BE\QueueExample\Rabbit\Worker;

$autoRebuild = true;
$loader = new Nette\DI\ContainerLoader(__DIR__ . '/temp', $autoRebuild);
$class = $loader->load(function($compiler) {
	$compiler->loadConfig(__DIR__ . '/config-rabbit.neon');
});
$container = new $class;

$worker = $container->getByType(Worker::class);

$worker->startWorker();

echo "job consumer done!";