<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\SimpleJob;
use BrandEmbassy\DateTime\DateTimeFromString;
use Doctrine\Common\Collections\ArrayCollection;
use Tests\BE\QueueManagement\Jobs\JobDefinitions\DummyJobDefinition;

class DummyJob extends SimpleJob
{
    public const UUID = 'some-job-uud';
    public const ATTEMPTS = JobInterface::INIT_ATTEMPTS;
    public const CREATED_AT = '2018-08-01T10:15:47+01:00';
    public const JOB_NAME = 'dummyJob';
    public const PARAMETER_FOO = 'foo';


    public function __construct(?JobDefinitionInterface $jobDefinition = null, string $bar = 'bar')
    {
        parent::__construct(
            self::UUID,
            DateTimeFromString::create(self::CREATED_AT),
            self::ATTEMPTS,
            $jobDefinition ?? DummyJobDefinition::create(),
            new ArrayCollection([self::PARAMETER_FOO => $bar])
        );
    }


    public function getFoo(): string
    {
        return $this->getParameter(self::PARAMETER_FOO);
    }
}
