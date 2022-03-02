<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class JobDefinitionsContainer
{
    /**
     * @var Collection<string, JobDefinitionInterface>|JobDefinitionInterface[]
     */
    private Collection $jobDefinitions;

    private JobDefinitionFactoryInterface $jobDefinitionFactory;

    /**
     * @var mixed[]
     */
    private array $jobDefinitionsConfig;


    /**
     * @param mixed[] $jobDefinitionsConfig
     */
    public function __construct(array $jobDefinitionsConfig, JobDefinitionFactoryInterface $jobDefinitionFactory)
    {
        $this->jobDefinitionFactory = $jobDefinitionFactory;
        $this->jobDefinitionsConfig = $jobDefinitionsConfig;
        $this->jobDefinitions = new ArrayCollection();
    }


    public function get(string $jobName): JobDefinitionInterface
    {
        $jobDefinition = $this->jobDefinitions->get($jobName);

        if ($jobDefinition === null) {
            return $this->loadJobDefinition($jobName);
        }

        return $jobDefinition;
    }


    public function has(string $jobName): bool
    {
        return $this->jobDefinitions->containsKey($jobName);
    }


    /**
     * @return Collection<string, JobDefinitionInterface>|JobDefinitionInterface[]
     */
    public function all(): Collection
    {
        return $this->jobDefinitions;
    }


    private function loadJobDefinition(string $jobName): JobDefinitionInterface
    {
        if (!isset($this->jobDefinitionsConfig[$jobName])) {
            throw UnknownJobDefinitionException::createFromUnknownJobName($jobName);
        }

        $jobDefinitionsConfig = $this->jobDefinitionsConfig[$jobName];

        $jobDefinition = $this->jobDefinitionFactory->create($jobName, $jobDefinitionsConfig);

        $this->jobDefinitions->set($jobName, $jobDefinition);

        return $jobDefinition;
    }
}
