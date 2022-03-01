<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Psr\Log\LoggerInterface;
use BE\QueueManagement\Redis\RedisClient;
use malkusch\lock\mutex\PredisMutex;
use malkusch\lock\exception\LockReleaseException;
 

/**
 * Redis based SQS message deduplicator. Combination of distributed locks (aka redlocks) with self-expiring keys is used
 */
final class MessageDeduplicationRedis implements MessageDeduplicationInterface
{

    private const REDIS_DEDUP_KEY_PREFIX = 'AWS_DEDUP_PREFIX_';
    private const PREDIS_MUTEX_NAME = 'predis_sqs_dedup_mutex';
    private const DEFAULT_DEDUP_INTERVAL_SEC = 300;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var RedisClient
     */    
    private $redisClient;

    /**
     * @var string
     */    
    private $queueName;


    /**
     * @var int
     */    
    private $lockExpirationTimeoutSec;
    
    public function __construct(
        LoggerInterface $logger,
        RedisClient $redisClient,
        int $lockExpirationTimeoutSec = 3,
        string $queueName
    ) {
        $this->logger = $logger;
        $this->redisClient = $redisClient;
        $this->lockExpirationTimeoutSec = $lockExpirationTimeoutSec;
        $this->queueName = $queueName;
    }

    public function isDuplicate(SqsMessage $message): bool
    {
        $mutex = new PredisMutex([$this->redisClient->getRedisClient()], self::PREDIS_MUTEX_NAME, $this->lockExpirationTimeoutSec);
        
        $messageId = $message->getMessageId();
        $redisClient = $this->redisClient;
        $queueName = $this->queueName;

        try {
            $isDuplicate = $mutex->check(function () use ($messageId, $redisClient, $queueName): bool {
                $rk = self::REDIS_DEDUP_KEY_PREFIX . $queueName . $messageId;
                $dedupKeyVal = $redisClient->get($rk);
                return $dedupKeyVal === null;
            })->then(function () use ($messageId, $redisClient, $queueName): int {
                $rk = self::REDIS_DEDUP_KEY_PREFIX . $queueName . $messageId;
                $redisClient->setWithTTL($rk, "1", self::DEFAULT_DEDUP_INTERVAL_SEC);
            
                return false;
            });        
    
            return $isDuplicate;

        } catch (LockReleaseException $unlockException) {
            $code_result = $unlockException->getCodeResult();

            if ($code_result !== null) {
                // LockReleaseException was thrown after sync block had been already executed
                // -> use sync block return value
                return $code_result;                
            } else {
                // if code result is not known we rather prefer to process message twice 
                // than to discard potentially unprocessed message -> indicate message has been not yet seen yet
                return false;
            }
        }
    }
}