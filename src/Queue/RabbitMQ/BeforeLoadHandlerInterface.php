<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use PhpAmqpLib\Message\AMQPMessage;

interface BeforeLoadHandlerInterface
{
    public function __invoke(RabbitMQConsumer $consumer, AMQPMessage $message): void;
}
