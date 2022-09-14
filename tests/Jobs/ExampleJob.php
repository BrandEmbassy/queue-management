<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\SimpleJob;
use BrandEmbassy\DateTime\DateTimeFromString;
use Doctrine\Common\Collections\ArrayCollection;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\ExampleJobDefinition;
use function str_repeat;

/**
 * @final
 */
class ExampleJob extends SimpleJob
{
    public const UUID = 'some-job-uud';
    public const ATTEMPTS = JobInterface::INIT_ATTEMPTS;
    public const CREATED_AT = '2018-08-01T10:15:47+01:00';
    public const JOB_NAME = 'exampleJob';
    public const PARAMETER_FOO = 'foo';
    public const EXECUTION_PLANNED_AT = '2018-08-01T10:40:00+00:00';


    public function __construct(?JobDefinitionInterface $jobDefinition = null, string $bar = 'bar')
    {
        parent::__construct(
            self::UUID,
            DateTimeFromString::create(self::CREATED_AT),
            self::ATTEMPTS,
            $jobDefinition ?? ExampleJobDefinition::create(),
            new ArrayCollection([self::PARAMETER_FOO => $bar]),
        );
    }


    public static function createTooBigForSqs(?JobDefinitionInterface $jobDefinition = null): self
    {
        return new self($jobDefinition, str_repeat('A', 262144)); // 256KB
    }


    public function getFoo(): string
    {
        return $this->getParameter(self::PARAMETER_FOO);
    }
}
