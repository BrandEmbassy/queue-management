<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\SimpleJob;
use BrandEmbassy\DateTime\DateTimeFromString;
use Doctrine\Common\Collections\ArrayCollection;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;
use function assert;
use function is_string;
use function str_repeat;

/**
 * @final
 */
class ExampleJob extends SimpleJob
{
    public const UUID = 'some-job-uuid';

    public const ATTEMPTS = JobInterface::INIT_ATTEMPTS;

    public const CREATED_AT = '2018-08-01T10:15:47+01:00';

    public const JOB_NAME = 'exampleJob';

    public const PARAMETER_FOO = 'foo';


    /**
     * @param array<string,array{DataType: string, StringValue?: string, BinaryValue?: string}> $messageAttributes
     */
    public function __construct(
        ?JobDefinitionInterface $jobDefinition = null,
        string $bar = 'bar',
        array $messageAttributes = [],
    ) {
        /**
         * Prevent phpstan error Template type T on class Doctrine\Common\Collections\Collection is not covariant
         * @var array<string,mixed> $parameters
         */
        $parameters = [self::PARAMETER_FOO => $bar];

        parent::__construct(
            self::UUID,
            DateTimeFromString::create(self::CREATED_AT),
            self::ATTEMPTS,
            $jobDefinition ?? ExampleJobDefinition::create(),
            new ArrayCollection($parameters),
            null,
            $messageAttributes,
        );
    }


    public static function createTooBigForSqs(?JobDefinitionInterface $jobDefinition = null): self
    {
        return new self($jobDefinition, str_repeat('A', 262144)); // 256KB
    }


    public function getFoo(): string
    {
        $foo = $this->getParameter(self::PARAMETER_FOO);

        assert(is_string($foo));

        return $foo;
    }
}
