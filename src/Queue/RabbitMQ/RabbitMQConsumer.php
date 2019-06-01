<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use BE\QueueManagement\Jobs\FailResolving\PushDelayedResolver;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionsContainer;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\Execution\ConsumerFailedExceptionInterface;
use BE\QueueManagement\Jobs\Execution\DelayableProcessFailExceptionInterface;
use BE\QueueManagement\Jobs\Execution\JobExecutor;
use BE\QueueManagement\Jobs\Execution\UnresolvableProcessFailExceptionInterface;
use BrandEmbassy\DateTime\DateTimeFromString;
use DateTime;
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
     * @var PushDelayedResolver
     */
    private $pushDelayedResolver;

    /**
     * @var JobExecutor
     */
    private $loadedJobHandler;

    /**
     * @var JobDefinitionsContainer
     */
    private $jobDefinitionsContainer;


    public function __construct(
        LoggerInterface $logger,
        JobDefinitionsContainer $jobDefinitionsContainer,
        JobExecutor $loadedJobHandler,
        PushDelayedResolver $pushDelayedResolver
    ) {
        $this->logger = $logger;
        $this->pushDelayedResolver = $pushDelayedResolver;
        $this->loadedJobHandler = $loadedJobHandler;
        $this->jobDefinitionsContainer = $jobDefinitionsContainer;
    }


    public function __invoke(AMQPMessage $message): void
    {
        /** @var AMQPChannel $channel */
        $channel = $message->delivery_info['channel'];

        try {
            $job = $this->loadJob($message->getBody());

            $this->loadedJobHandler->execute($job);

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

            $channel->basic_nack($message->delivery_info['delivery_tag']);
        }
    }


    public function loadJob(string $messageBody): JobInterface
    {
        $messageParameters = Json::decode($messageBody, Json::FORCE_ARRAY);

        $jobDefinition = $this->jobDefinitionsContainer->get($messageParameters[JobInterface::JOB_NAME]);

        $jobLoader = $jobDefinition->getJobLoader();

        return $jobLoader->load(
            $jobDefinition,
            $messageParameters[JobInterface::UUID],
            DateTimeFromString::create(
                DateTime::ATOM,
                $messageParameters[JobInterface::CREATED_AT]
            ),
            $messageParameters[JobInterface::ATTEMPTS],
            $messageParameters[JobInterface::PARAMETERS]
        );
    }
}
