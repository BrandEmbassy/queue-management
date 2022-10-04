<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use function array_key_exists;
use function count;
use function usleep;

/**
 * @final
 */
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

    private const CREATE_CONNECTION_ATTEMPT_DELAYS_IN_MILLISECONDS = [
        1 => 100,
        2 => 500,
        3 => 1000,
    ];

    /**
     * @var mixed[]
     */
    private array $connectionConfig;

    private LoggerInterface $logger;


    /**
     * @param mixed[] $connectionConfig
     */
    public function __construct(array $connectionConfig, ?LoggerInterface $logger = null)
    {
        if ($logger === null) {
            $logger = new NullLogger();
        }

        $this->connectionConfig = $connectionConfig;
        $this->logger = $logger;
    }


    public function create(): AMQPStreamConnection
    {
        $this->checkConfig($this->connectionConfig);

        try {
            return $this->createConnectionOrRetry();
        } catch (Throwable $exception) {
            throw ConnectionException::createUnableToConnect($exception);
        }
    }


    private function createConnectionOrRetry(int $attempt = 1): AMQPStreamConnection
    {
        try {
            return new AMQPStreamConnection(
                $this->connectionConfig[self::HOST],
                $this->connectionConfig[self::PORT],
                $this->connectionConfig[self::USER],
                $this->connectionConfig[self::PASSWORD],
                $this->connectionConfig[self::VHOST] ?? '/',
                $this->connectionConfig[self::INSIST] ?? false,
                $this->connectionConfig[self::LOGIN_METHOD] ?? 'AMQPLAIN',
                $this->connectionConfig[self::LOGIN_RESPONSE] ?? null,
                $this->connectionConfig[self::LOCALE] ?? 'en_US',
                $this->connectionConfig[self::CONNECTION_TIMEOUT] ?? 3.0,
                $this->connectionConfig[self::READ_WRITE_TIMEOUT] ?? 3.0,
                $this->connectionConfig[self::CONTEXT] ?? null,
                $this->connectionConfig[self::KEEP_ALIVE] ?? false,
                $this->connectionConfig[self::HEART_BEAT] ?? 0,
                $this->connectionConfig[self::CHANNEL_RPC_TIMEOUT] ?? 0.0,
                $this->connectionConfig[self::SSL_PROTOCOL] ?? null,
            );
        } catch (Throwable $exception) {
            if (!isset(self::CREATE_CONNECTION_ATTEMPT_DELAYS_IN_MILLISECONDS[$attempt])) {
                $this->logger->error('RabbitMQ connection creation failed. Giving up.', [
                    'attempt' => $attempt,
                ]);

                throw $exception;
            }

            $delayInMilliseconds = self::CREATE_CONNECTION_ATTEMPT_DELAYS_IN_MILLISECONDS[$attempt];
            $this->logger->warning('RabbitMQ connection creation failed. Waiting and retrying...', [
                'attempt' => $attempt,
                'delayInMilliseconds' => $delayInMilliseconds,
                'exception' => $exception,
            ]);

            usleep($delayInMilliseconds * 1000);

            return $this->createConnectionOrRetry($attempt + 1);
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
