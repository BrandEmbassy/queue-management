<?php declare(strict_types = 1);

namespace BE\QueueExample\Rabbit;

use BE\QueueManagement\Jobs\SimpleJob;

class ExampleJob extends SimpleJob
{
    public const JOB_NAME = 'exampleJob';
    public const PARAMETER_FOO = 'foo';


    public function getFoo(): string
    {
        return $this->getParameter(self::PARAMETER_FOO);
    }
}