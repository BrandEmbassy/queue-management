<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Jobs\FailResolving\PushDelayedResolver;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\Loading\JobLoadersMapInterface;
use BE\QueueManagement\Jobs\Processing\ConsumerFailedExceptionInterface;
use BE\QueueManagement\Jobs\Processing\DelayableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Processing\LoadedJobHandler;
use BE\QueueManagement\Jobs\Processing\UnresolvableProcessFailExceptionInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Nette\Utils\Json;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class RabbitMQConsumer
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var JobLoadersMapInterface
     */
    private $jobLoadersMap;

    /**
     * @var PushDelayedResolver
     */
    private $pushDelayedResolver;

    /**
     * @var LoadedJobHandler
     */
    private $loadedJobHandler;

    /**
     * @var array|BeforeLoadHandlerInterface[]
     */
    private $beforeLoadHandlers;


    /**
     * @param BeforeLoadHandlerInterface[] $beforeLoadHandlers
     */
    public function __construct(
        array $beforeLoadHandlers,
        LoggerInterface $logger,
        JobLoadersMapInterface $jobLoadersMap,
        LoadedJobHandler $loadedJobHandler,
        PushDelayedResolver $pushDelayedResolver
    ) {
        $this->logger = $logger;
        $this->jobLoadersMap = $jobLoadersMap;
        $this->pushDelayedResolver = $pushDelayedResolver;
        $this->loadedJobHandler = $loadedJobHandler;
        $this->beforeLoadHandlers = $beforeLoadHandlers;
    }


    public function __invoke(AMQPMessage $message): void
    {
        $this->beforeLoad($message);

        /** @var AMQPChannel $channel */
        $channel = $message->delivery_info['channel'];

        try {
            $job = $this->loadJob($message->getBody());

            $this->loadedJobHandler->handle($job);

            $channel->basic_ack($message->delivery_info['delivery_tag']);
        } catch (DelayableProcessFailExceptionInterface $exception) {
            $this->pushDelayedResolver->resolve($exception->getJob(), $exception);
        } catch (ConsumerFailedExceptionInterface $exception) {
            $this->logger->error(
                'Job rejected from queue: ' . $exception->getMessage(),
                ['exception' => $exception]
            );

            $channel->basic_reject($message->delivery_info['delivery_tag'], true);

            throw $exception;
        } catch (UnresolvableProcessFailExceptionInterface $exception) {
            $this->logger->error(
                'Job removed from queue: ' . $exception->getMessage(),
                ['exception' => $exception]
            );

            $channel->basic_nack($message->delivery_info['delivery_tag'], true);
        }
    }


    private function beforeLoad(AMQPMessage $message): void
    {
        foreach ($this->beforeLoadHandlers as $consumerHealthCheck) {
            $consumerHealthCheck($this, $message);
        }
    }


    public function loadJob(string $queueMessage): JobInterface
    {
        $decodedMessage = Json::decode($queueMessage, Json::FORCE_ARRAY);

        $jobLoader = $this->jobLoadersMap->getJobLoader($decodedMessage[JobInterface::JOB_NAME]);

        $parameters = new ArrayCollection($decodedMessage[JobInterface::PARAMETERS]);

        return $jobLoader->load(
            $decodedMessage[JobInterface::UUID],
            $decodedMessage[JobInterface::JOB_NAME],
            $decodedMessage[JobInterface::ATTEMPTS],
            $parameters
        );
    }
}
