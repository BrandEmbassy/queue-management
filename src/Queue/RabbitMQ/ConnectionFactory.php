<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Throwable;
use function array_key_exists;
use function count;

class ConnectionFactory implements ConnectionFactoryInterface
{
    private const HOST = 'host';
    private const PORT = 'port';
    private const USER = 'user';
    private const PASSWORD = 'password';
    private const VHOST = 'vhost';
    private const INSIST = 'insist';
    private const LOGIN_METHOD = 'loginMethod';
    private const LOGIN_RESPONSE = 'loginResponse';
    private const LOCALE = 'locale';
    private const CONNECTION_TIMEOUT = 'connectionTimeout';
    private const READ_WRITE_TIMEOUT = 'readWriteTimeout';
    private const CONTEXT = 'context';
    private const KEEP_ALIVE = 'keepAlive';
    private const HEART_BEAT = 'heartBeat';
    private const CHANNEL_RPC_TIMEOUT = 'channelRpcTimeout';
    private const SSL_PROTOCOL = 'sslProtocol';

    /**
     * @var mixed[]
     */
    private $connectionConfig;


    /**
     * @param mixed[] $connectionConfig
     */
    public function __construct(array $connectionConfig)
    {
        $this->connectionConfig = $connectionConfig;
    }


    public function create(): AMQPStreamConnection
    {
        $connectionConfig = $this->connectionConfig;

        $this->checkConfig($connectionConfig);

        try {
            return new AMQPStreamConnection(
                $connectionConfig[self::HOST],
                $connectionConfig[self::PORT],
                $connectionConfig[self::USER],
                $connectionConfig[self::PASSWORD],
                $connectionConfig[self::VHOST] ?? '/',
                $connectionConfig[self::INSIST] ?? false,
                $connectionConfig[self::LOGIN_METHOD] ?? 'AMQPLAIN',
                $connectionConfig[self::LOGIN_RESPONSE] ?? null,
                $connectionConfig[self::LOCALE] ?? 'en_US',
                $connectionConfig[self::CONNECTION_TIMEOUT] ?? 3.0,
                $connectionConfig[self::READ_WRITE_TIMEOUT] ?? 3.0,
                $connectionConfig[self::CONTEXT] ?? null,
                $connectionConfig[self::KEEP_ALIVE] ?? false,
                $connectionConfig[self::HEART_BEAT] ?? 0,
                $connectionConfig[self::CHANNEL_RPC_TIMEOUT] ?? 0.0,
                $connectionConfig[self::SSL_PROTOCOL] ?? null
            );
        } catch (Throwable $exception) {
            throw ConnectionException::createUnableToConnect($exception);
        }
    }


    /**
     * @param string[]|int[] $connectionConfig
     */
    private function checkConfig(array $connectionConfig): void
    {
        $requiredKeys = [
            self::HOST,
            self::PORT,
            self::USER,
            self::PASSWORD,
        ];

        $missing = [];

        /** @var string $requiredKey */
        foreach ($requiredKeys as $requiredKey) {
            if (array_key_exists($requiredKey, $connectionConfig)) {
                continue;
            }

            $missing[] = $requiredKey;
        }

        if (count($missing) > 0) {
            throw ConnectionException::createFromMissingParameters($missing);
        }
    }
}
