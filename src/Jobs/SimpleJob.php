<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\Execution\MaximumAttemptsExceededException;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionInterface;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Nette\Utils\Json;
use function array_merge;

class SimpleJob implements JobInterface
{
    /**
     * @var string
     */
    private $uuid;

    /**
     * @var DateTimeImmutable
     */
    private $createdAt;

    /**
     * @var int
     */
    private $attempts;

    /**
     * @var Collection<string, mixed>|mixed[]
     */
    private $parameters;

    /**
     * @var DateTimeImmutable|null
     */
    protected $executionStartedAt;

    /**
     * @var JobDefinitionInterface
     */
    protected $jobDefinition;


    /**
     * @param Collection<string, mixed>|mixed[] $parameters
     */
    public function __construct(
        string $uuid,
        DateTimeImmutable $createdAt,
        int $attempts,
        JobDefinitionInterface $jobDefinition,
        Collection $parameters
    ) {
        $this->uuid = $uuid;
        $this->createdAt = $createdAt;
        $this->attempts = $attempts;
        $this->parameters = $parameters;
        $this->jobDefinition = $jobDefinition;
    }


    public function getUuid(): string
    {
        return $this->uuid;
    }


    public function getName(): string
    {
        return $this->jobDefinition->getJobName();
    }


    public function getAttempts(): int
    {
        return $this->attempts;
    }


    /**
     * @param mixed[] $customParameters
     */
    public function toJson(array $customParameters = []): string
    {
        $arrayData = [
            JobParameters::UUID       => $this->uuid,
            JobParameters::JOB_NAME   => $this->getName(),
            JobParameters::ATTEMPTS   => $this->attempts,
            JobParameters::CREATED_AT => $this->createdAt->format(DateTime::ATOM),
            JobParameters::PARAMETERS => $this->parameters->toArray(),
        ];

        return Json::encode(array_merge($arrayData, $customParameters));
    }


    /**
     * @param string|int|mixed[]|null $value
     */
    protected function setParameter(string $key, $value): void
    {
        $this->parameters->set($key, $value);
    }


    /**
     * @return mixed
     */
    public function getParameter(string $key)
    {
        if ($this->parameters->containsKey($key)) {
            return $this->parameters->get($key);
        }

        throw JobValidationException::createFromUnknownParameter($key, $this->parameters->getKeys(), $this);
    }


    public function incrementAttempts(): void
    {
        $this->attempts++;
        $maxAttempts = $this->getMaxAttempts();

        if ($maxAttempts !== null && $this->attempts > $maxAttempts) {
            throw MaximumAttemptsExceededException::createFromAttemptsLimit($maxAttempts);
        }

        $this->setParameter(JobParameters::ATTEMPTS, $this->attempts);
    }


    public function executionStarted(DateTimeImmutable $startedAt): void
    {
        $this->executionStartedAt = $startedAt;
    }


    public function getExecutionStartedAt(): ?DateTimeImmutable
    {
        return $this->executionStartedAt;
    }


    public function getMaxAttempts(): ?int
    {
        return $this->jobDefinition->getMaxAttempts();
    }


    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }


    public function getJobDefinition(): JobDefinitionInterface
    {
        return $this->jobDefinition;
    }
}
