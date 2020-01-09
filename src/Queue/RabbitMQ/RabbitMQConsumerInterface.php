<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use PhpAmqpLib\Message\AMQPMessage;

interface RabbitMQConsumerInterface
{
    public function __invoke(AMQPMessage $message): void;
}
