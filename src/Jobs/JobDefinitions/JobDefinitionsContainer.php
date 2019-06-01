<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Exception;

class JobDefinitionsContainer
{
    /**
     * @var Collection[]|JobDefinitionInterface[]
     */
    private $jobDefinitions;

    /**
     * @var JobDefinitionFactoryInterface
     */
    private $jobDefinitionFactory;


    public function __construct(array $jobDefinitionsConfig, JobDefinitionFactoryInterface $jobDefinitionFactory)
    {
        $this->jobDefinitionFactory = $jobDefinitionFactory;
        $this->jobDefinitions = $this->loadJobDefinitions($jobDefinitionsConfig);
    }


    public function get(string $jobName): JobDefinitionInterface
    {
        $jobDefinition = $this->jobDefinitions->get($jobName);

        if ($jobDefinition === null) {
            throw new Exception('TBD');
        }

        return $jobDefinition;
    }


    private function loadJobDefinitions(array $jobDefinitionsConfig): Collection
    {
        $jobDefinitions = new ArrayCollection();

        foreach ($jobDefinitionsConfig as $jobName => $jobDefinition) {
            $jobDefinitions->set($jobName, $this->jobDefinitionFactory->create($jobName, $jobDefinition));
        }

        return $jobDefinitions;
    }
}
