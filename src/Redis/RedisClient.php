<?php declare(strict_types = 1);

namespace BE\QueueManagement\Redis;

use Predis\ClientInterface;
use Predis\Response\Status;
use Throwable;

/**
 * TODO:  extract into a separate package
 * {@see https://github.com/BrandEmbassy/platform-backend/blob/master/application/src/BE/Database/Redis/RedisClient.php}
 *
 * @final
 */
class RedisClient
{
    private const SAVE_SUCCESS = 'OK';

    private ClientInterface $client;


    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }


    /**
     * @throws RedisClientException
     */
    public function setWithTtl(
        string $key,
        string $value,
        int $timeToLiveSeconds
    ): bool {
        try {
            $result = $this->client->set($key, $value, 'EX', $timeToLiveSeconds, 'NX');
        } catch (Throwable $exception) {
            throw RedisClientException::byAnotherExceptionWhenSettingValueWithTtl($exception);
        }

        return $this->isSavingSucceeded($result);
    }


    /**
     * @param mixed $result
     *
     * @throws RedisClientException
     */
    private function isSavingSucceeded($result): bool
    {
        // null means that key already exists
        if ($result === null) {
            return false;
        }

        if (!$result instanceof Status) {
            throw RedisClientException::byInvalidResultStatus((string)$result);
        }

        if ($result->getPayload() !== self::SAVE_SUCCESS) {
            throw RedisClientException::byInvalidSavedStatus($result->getPayload());
        }

        return true;
    }
}
