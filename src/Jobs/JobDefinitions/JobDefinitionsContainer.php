<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\JobDefinitions;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @phpstan-import-type TJobDefinition from JobDefinition
 */
class JobDefinitionsContainer
{
    /**
     * @var array<string, TJobDefinition>
     */
    private array $jobDefinitionsConfig;

    private JobDefinitionFactoryInterface $jobDefinitionFactory;

    /**
     * @var Collection<string, JobDefinitionInterface>|JobDefinitionInterface[]|null
     */
    private ?Collection $jobDefinitions = null;


    /**
     * @param array<string, TJobDefinition> $jobDefinitionsConfig
     */
    public function __construct(array $jobDefinitionsConfig, JobDefinitionFactoryInterface $jobDefinitionFactory)
    {
        $this->jobDefinitionsConfig = $jobDefinitionsConfig;
        $this->jobDefinitionFactory = $jobDefinitionFactory;
    }


    public function get(string $jobName): JobDefinitionInterface
    {
        $jobDefinition = $this->all()->get($jobName);

        if ($jobDefinition === null) {
            throw UnknownJobDefinitionException::createFromUnknownJobName($jobName);
        }

        return $jobDefinition;
    }


    public function has(string $jobName): bool
    {
        return $this->all()->containsKey($jobName);
    }


    /**
     * @return Collection<string, JobDefinitionInterface>|JobDefinitionInterface[]
     */
    public function all(): Collection
    {
        if ($this->jobDefinitions === null) {
            $this->jobDefinitions = $this->loadJobDefinitions($this->jobDefinitionsConfig);
        }

        return clone $this->jobDefinitions;
    }


    /**
     * @param array<string, TJobDefinition> $jobDefinitionsConfig
     *
     * @return Collection<string, JobDefinitionInterface>|JobDefinitionInterface[]
     */
    private function loadJobDefinitions(array $jobDefinitionsConfig): Collection
    {
        $jobDefinitions = new ArrayCollection();

        foreach ($jobDefinitionsConfig as $jobName => $jobDefinitionConfig) {
            $jobDefinition = $this->jobDefinitionFactory->create($jobName, $jobDefinitionConfig);

            $jobDefinitions->set($jobName, $jobDefinition);
        }

        return $jobDefinitions;
    }
}
