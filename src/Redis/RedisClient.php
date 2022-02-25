<?php declare(strict_types = 1);

namespace BE\QueueManagement\Redis;

use Predis\Client;
use Predis\Response\Status;
use Throwable;
use function sprintf;

final class RedisClient
{

    private const SAVE_SUCCESS = 'OK';

    private Client $client;


    public function __construct(Client $client)
    {
        $this->client = $client;
    }


    /**
     * @throws RedisClientException
     */
    public function set(string $key, string $value): void
    {
        try {
            $result = $this->client->set($key, $value);
        } catch (Throwable $exception) {
            $message = sprintf('Unexpected exception during value setting: %s', $exception->getMessage());

            throw new RedisClientException($message, $exception->getCode(), $exception);
        }

        $this->assertSavingSucceeded($result);
    }


    /**
     * @throws RedisClientException
     */
    public function setWithTTL(
        string $key,
        string $value,
        RedisKeyExpirationTimeResolutionKeyword $keyExpireTimeResolution,
        int $timeToLive
    ): void {
        try {
            $result = $this->client->set($key, $value, 'EX', $timeToLive);
        } catch (Throwable $exception) {
            $message = sprintf(
                'Unexpected exception during setting of value with time to live: %s',
                $exception->getMessage()
            );

            throw new RedisClientException($message, $exception->getCode(), $exception);
        }

        $this->assertSavingSucceeded($result);
    }



    /**
     * @throws RedisClientException
     */
    public function get(string $key): ?string
    {
        try {
            $fetchedValue = $this->client->get($key);
        } catch (Throwable $exception) {
            $message = sprintf('Unexpected exception during value getting: %s', $exception->getMessage());

            throw new RedisClientException($message, $exception->getCode(), $exception);
        }

        $this->assertFetchedValueIsValid($fetchedValue);

        return $fetchedValue;
    }


    /**
     * @param mixed $result
     *
     * @throws RedisClientException
     */
    private function assertSavingSucceeded($result): void
    {
        if (!$result instanceof Status) {
            $message = sprintf(
                'Invalid response from Redis client during value setting. Response value: %s',
                (string)$result
            );

            throw new RedisClientException($message);
        }

        if ($result->getPayload() !== self::SAVE_SUCCESS) {
            $message = sprintf('Saving failed. Response payload value: %s.', $result->getPayload());

            throw new RedisClientException($message);
        }
    }

   public function getRedisClient(): Client
   {
       return $this->client;
   }
}
