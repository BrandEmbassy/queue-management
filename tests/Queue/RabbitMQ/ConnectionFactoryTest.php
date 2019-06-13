<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Queue\RabbitMQ\ConnectionException;
use BE\QueueManagement\Queue\RabbitMQ\ConnectionFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class ConnectionFactoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;


    public function testUnableToEstablishConnectionThrowsException(): void
    {
        $connectionFactory = new ConnectionFactory(
            [
                ConnectionFactory::HOST     => 'localhost',
                ConnectionFactory::PORT     => 6666,
                ConnectionFactory::PASSWORD => 'admin',
                ConnectionFactory::USER     => 'root',
            ]
        );

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Unable to connect RabbitMQ server, try check connection parameters');

        $connectionFactory->create();
    }


    public function testMissingConfigKeysThrowsException(): void
    {
        $connectionFactory = new ConnectionFactory(
            [
                ConnectionFactory::HOST => 'localhost',
                ConnectionFactory::PORT => 6666,
            ]
        );

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Invalid connection config for RabbitMQ server, missing key/s (user, password)');

        $connectionFactory->create();
    }
}
