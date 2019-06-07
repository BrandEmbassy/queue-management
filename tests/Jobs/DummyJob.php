<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use BE\QueueManagement\Jobs\SimpleJob;
use BrandEmbassy\DateTime\DateTimeFromString;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;

class DummyJob extends SimpleJob
{
    public const JOB_UUID = 'some-job-uud';
    public const ATTEMPTS = 1;
    public const CREATED_AT = '2018-08-01T10:15:47+01:00';
    public const JOB_NAME = 'dummyJob';
    public const PARAMETER_FOO = 'foo';


    public function __construct(JobDefinitionInterface $jobDefinition, string $bar = 'bar')
    {
        parent::__construct(
            self::JOB_UUID,
            DateTimeFromString::create(DateTime::ATOM, self::CREATED_AT),
            self::ATTEMPTS,
            $jobDefinition,
            new ArrayCollection([self::PARAMETER_FOO => $bar])
        );
    }


    public function getFoo(): string
    {
        return $this->getParameter(self::PARAMETER_FOO);
    }
}
