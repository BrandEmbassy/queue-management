<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use Psr\Log\LoggerInterface;
use BE\QueueManagement\Redis\RedisClient;
use malkusch\lock\mutex\Mutex;
use malkusch\lock\exception\LockReleaseException;
 

/**
 * Default SQS message deduplicator. Combination of locks (via php-lock/lock) with self-expiring redis keys is used
 * to deliver fixed-size deduplication time window/frame for SQS message (messageId used as deduplication key).
 */
final class MessageDeduplicationDefault implements MessageDeduplicationInterface
{

    private const DEDUP_KEY_PREFIX = 'AWS_DEDUP_PREFIX_';

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
     * @var Mutex
     */    
    private $mutex;

    /**
     * @var int
     */
    private $dedupWindowSizeSec;

    
    public function __construct(
        LoggerInterface $logger,
        RedisClient $redisClient,
        Mutex $mutex,
        string $queueName,
        int $dedupWindowSizeSec = 300
    ) {
        $this->logger = $logger;
        $this->redisClient = $redisClient;
        $this->queueName = $queueName;
        $this->mutex = $mutex;
        $this->dedupWindowSizeSec = $dedupWindowSizeSec;
    }

    public function isDuplicate(SqsMessage $message): bool
    {
        $mutex = $this->mutex;
        
        $messageId = $message->getMessageId();
        $redisClient = $this->redisClient;
        $queueName = $this->queueName;
        $dedupWindowSizeSec = $this->dedupWindowSizeSec;

        try {
            $alreadySeen = $mutex->synchronized(function () use ($messageId, $redisClient, $queueName, $dedupWindowSizeSec): bool {
                $rk = self::DEDUP_KEY_PREFIX . $queueName . $messageId;
                $dedupKeyVal = $redisClient->get($rk);
                if ($dedupKeyVal === null) {
                    $redisClient->setWithTTL($rk, "1", $dedupWindowSizeSec);
                    return false;
                } else {
                    return true;
                }
            });        
    
            return $alreadySeen;

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