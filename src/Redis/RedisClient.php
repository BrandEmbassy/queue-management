<?php declare(strict_types = 1);

namespace BE\QueueManagement\Redis;

use Predis\Client;
use Predis\Response\Status;
use Throwable;
use function gettype;
use function is_string;

/**
 * TODO:  extract into a separate package
 * {@see https://github.com/BrandEmbassy/platform-backend/blob/master/application/src/BE/Database/Redis/RedisClient.php}
 *
 * @final
 */
class RedisClient
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
            throw RedisClientException::byAnotherExceptionWhenSettingValue($exception);
        }

        $this->assertSavingSucceeded($result);
    }


    /**
     * @throws RedisClientException
     */
    public function setWithTtl(
        string $key,
        string $value,
        int $timeToLiveSeconds
    ): void {
        try {
            $result = $this->client->set($key, $value, 'EX', $timeToLiveSeconds);
        } catch (Throwable $exception) {
            throw RedisClientException::byAnotherExceptionWhenSettingValueWithTtl($exception);
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
            throw RedisClientException::byAnotherExceptionWhenGettingValue($exception);
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
            throw RedisClientException::byInvalidResultStatus((string)$result);
        }

        if ($result->getPayload() !== self::SAVE_SUCCESS) {
            throw RedisClientException::byInvalidSavedStatus($result->getPayload());
        }
    }


    /**
     * @param mixed $fetchedValue
     *
     * @throws RedisClientException
     */
    private function assertFetchedValueIsValid($fetchedValue): void
    {
        $isValidValue = $this->checkFetchedValueIsValid($fetchedValue);

        if (!$isValidValue) {
            throw RedisClientException::byInvalidReturnValue(gettype($fetchedValue));
        }
    }


    /**
     * @param mixed $fetchedValue
     */
    public function checkFetchedValueIsValid($fetchedValue): bool
    {
        return $fetchedValue === null || is_string($fetchedValue);
    }
}
