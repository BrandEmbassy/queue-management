<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Throwable;
use function array_key_exists;
use function count;

class ConnectionFactory implements ConnectionFactoryInterface
{
    public const HOST = 'host';
    public const PORT = 'port';
    public const USER = 'user';
    public const PASSWORD = 'password';
    public const VHOST = 'vhost';
    public const INSIST = 'insist';
    public const LOGIN_METHOD = 'loginMethod';
    public const LOGIN_RESPONSE = 'loginResponse';
    public const LOCALE = 'locale';
    public const CONNECTION_TIMEOUT = 'connectionTimeout';
    public const READ_WRITE_TIMEOUT = 'readWriteTimeout';
    public const CONTEXT = 'context';
    public const KEEP_ALIVE = 'keepAlive';
    public const HEART_BEAT = 'heartBeat';
    public const CHANNEL_RPC_TIMEOUT = 'channelRpcTimeout';
    public const SSL_PROTOCOL = 'sslProtocol';

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
