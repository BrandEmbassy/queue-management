<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use PhpAmqpLib\Connection\AMQPStreamConnection;

interface ConnectionFactoryInterface
{
    public function create(): AMQPStreamConnection;
}
