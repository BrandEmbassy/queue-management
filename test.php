<?php declare(strict_types = 1);

use BE\QueueManagement\Queue\AWSSQS\MessageDeduplication\MessageDeduplicationDefault;
use BE\QueueManagement\Queue\AWSSQS\SqsMessage;
use BE\QueueManagement\Redis\RedisClient;
use Predis\Client;
use Psr\Log\NullLogger;

require_once __DIR__ . '/vendor/autoload.php';

$predisClient = new Client([
    'host' => 'localhost',
    'port' => '6379',
]);

$redisClient = new RedisClient($predisClient);

$messageDeduplication = new MessageDeduplicationDefault($redisClient, new NullLogger());

$sqsMessage = new SqsMessage(['messageId' => '1234'], 'foo.bar');

$messageDeduplication->isDuplicate($sqsMessage);