<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs;

use BE\QueueManagement\Jobs\Processing\MaximumAttemptsExceededException;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Nette\Utils\Json;
use function implode;
use function sprintf;

abstract class AbstractJob implements JobInterface
{
    /**
     * @var string
     */
    private $uuid;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $attempts;

    /**
     * @var Collection|mixed[]
     */
    private $parameters;

    /**
     * @var DateTimeImmutable|null
     */
    private $executionStartedAt;


    /**
     * @param Collection|mixed[] $parameters
     */
    public function __construct(string $uuid, string $name, int $attempts, Collection $parameters)
    {
        $this->uuid = $uuid;
        $this->name = $name;
        $this->attempts = $attempts;
        $this->parameters = $parameters;
    }


    public function getUuid(): string
    {
        return $this->uuid;
    }


    public function getName(): string
    {
        return $this->name;
    }


    public function getAttempts(): int
    {
        return $this->attempts;
    }


    public function toJson(): string
    {
        $arrayData = [
            self::UUID       => $this->uuid,
            self::JOB_NAME   => $this->name,
            self::JOB_CLASS  => static::class,
            self::ATTEMPTS   => $this->attempts,
            self::PARAMETERS => $this->parameters->toArray(),
        ];

        return Json::encode($arrayData);
    }


    /**
     * @param mixed $value
     */
    public function setParameter(string $key, $value): void
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

        throw new JobValidationException(
            sprintf(
                'Parameter %s not found, available parameters: %s',
                $key,
                implode(', ', $this->parameters->getKeys())
            ),
            $this
        );
    }


    public function incrementAttempts(): void
    {
        $incremented = $this->getAttempts() + 1;
        $maxAttempts = $this->getMaxAttempts();

        if ($maxAttempts !== null && $incremented > $maxAttempts) {
            throw new MaximumAttemptsExceededException(sprintf('Maximum limit (%s) attempts exceeded', $maxAttempts));
        }

        $this->setParameter(self::ATTEMPTS, $incremented);
    }


    public function executionStarted(DateTimeImmutable $startedAt): void
    {
        $this->executionStartedAt = $startedAt;
    }


    public function getExecutionStartedAt(): ?DateTimeImmutable
    {
        return $this->executionStartedAt;
    }


    abstract public function getQueueName(): string;


    abstract public function getMaxAttempts(): ?int;
}
