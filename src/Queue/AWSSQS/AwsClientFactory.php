<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use function array_key_exists;

/**
 * Contains technical validation common for all AWS SDK clients (e.g. SqsClient, S3Client, etc.)
 */
class AwsClientFactory
{
    public const VERSION = 'version';
    public const REGION = 'region';

    /**
     * @var mixed[]
     */
    protected array $connectionConfig;


    /**
     * @param mixed[] $connectionConfig
     */
    public function __construct(array $connectionConfig)
    {
        $this->connectionConfig = $connectionConfig;
    }


    /**
     * @return array<string>
     */
    protected function getMissingRequiredElements(): array
    {
        $requiredKeys = [
            self::VERSION,
            self::REGION,
        ];

        $missing = [];

        /** @var string $requiredKey */
        foreach ($requiredKeys as $requiredKey) {
            if (array_key_exists($requiredKey, $this->connectionConfig)) {
                continue;
            }

            $missing[] = $requiredKey;
        }

        return $missing;
    }
}
