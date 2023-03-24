<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use BE\QueueManagement\Jobs\JobInterface;

/**
 * @final
 */
class MessageKeyGenerator implements MessageKeyGeneratorInterface
{
    private string $serviceId;


    /**
     * @param string $serviceId - Identifier of a service using this library e.g. "platform-backend", "instagram", ...
     */
    public function __construct(string $serviceId)
    {
        $this->serviceId = $serviceId;
    }


    public function generate(JobInterface $job): string
    {
        return $this->serviceId . '/sqs-messages/' . $job->getName() . '/' . $job->getUuid() . '.json';
    }
}
