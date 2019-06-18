<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

class JobParameters
{
    public const UUID = 'jobUuid';
    public const JOB_NAME = 'jobName';
    public const ATTEMPTS = 'attempts';
    public const CREATED_AT = 'createdAt';
    public const PARAMETERS = 'jobParameters';
}
