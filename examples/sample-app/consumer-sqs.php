<?php declare(strict_types = 1);

require __DIR__ . '/vendor/autoload.php';

use BE\QueueExample\Sqs\CustomSqsWorker;

$autoRebuild = true;
$loader = new Nette\DI\ContainerLoader(__DIR__ . '/temp', $autoRebuild);
$class = $loader->load(function($compiler) {
	$compiler->loadConfig(__DIR__ . '/config-sqs.neon');
});
$container = new $class;

$worker = $container->getByType(CustomSqsWorker::class);

$worker->startWorker();

echo "job consumer done!";