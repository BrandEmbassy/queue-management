<?php declare(strict_types = 1);

namespace BE\QueueExample\Sqs;

use BE\QueueManagement\Jobs\SimpleJob;

class SampleSqsJob extends SimpleJob
{
    public const JOB_NAME = 'exampleSqsJob';
    public const PARAMETER_FOO = 'foo';


    public function getFoo(): string
    {
        return $this->getParameter(self::PARAMETER_FOO);
    }
}