<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

interface JobDefinitionsContainerAccessor
{
    public function get(): JobDefinitionsContainer;
}
