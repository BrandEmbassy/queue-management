<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

/**
 * @phpstan-type TJobParameters array{
 *     jobUuid: string,
 *     jobName: string,
 *     attempts: int,
 *     createdAt: string,
 *     jobParameters: mixed[],
 *     executionPlannedAt?: ?string
 * }
 */
class JobParameters
{
    public const UUID = 'jobUuid';

    public const JOB_NAME = 'jobName';

    public const ATTEMPTS = 'attempts';

    public const CREATED_AT = 'createdAt';

    public const PARAMETERS = 'jobParameters';

    public const EXECUTION_PLANNED_AT = 'executionPlannedAt';
}
