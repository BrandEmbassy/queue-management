# Queue management

## Usage

### 1. Create Job class

```php
<?php declare(strict_types = 1);

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

```

### 2. Create job processor
```php
<?php declare(strict_types = 1);

use BE\QueueManagement\Jobs\Execution\JobProcessorInterface;
use BE\QueueManagement\Jobs\JobInterface;
use YourApp\Jobs\ExampleJob;

class ExampleJobProcessor implements JobProcessorInterface
{
    public function process(JobInterface $job): void
    {
        assert($job instanceof ExampleJob);
        
        echo $job->getFoo();
    }
}
```

### 3. Create job definition

For example using neon DI:

```yml
parameters:
    queue:
        jobs:
            defaultJobLoader: BE\QueueManagement\Jobs\Loading\SimpleJobLoader()
            jobDefinitions:
                exampleJob:
                    class: YourApp\Jobs\ExampleJob
                    queueName: example_queue
                    maxAttempts: 20 # null means no limit
                    jobLoader: YourApp\JobLoaders\ExampleJobLoader() # if not set default job loader is used
                    jobFailResolveStrategy: \BE\QueueManagement\Jobs\FailResolving\FailResolveStrategy\ConstantDelayInSecondsFailResolveStrategy(10)
                    jobProcessor: @queue.processors.exampleJobProcessor

services:
    queue.processors.exampleJobProcessor: YourApp\JobProcessors\ExampleJobProcessor 

    # JobDefinitionsContainer
    queue.jobDefinitionsContainer: BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionsContainer(%queue.jobs.jobDefinitions%)
```

### 4. Push job into queue

```php
<?php declare(strict_types = 1);

use BE\QueueManagement\Queue\QueueManagerInterface;
use YourApp\Jobs\ExampleJob;
use BE\QueueManagement\Jobs\JobInterface;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionsContainer;

class JobPusher
{

    /**
     * @var QueueManagerInterface
     */
    protected $queueManager;

    /**
     * @var JobDefinitionsContainer
     */
    private $jobDefinitionsContainer;


    public function __construct(QueueManagerInterface $queueManager, JobDefinitionsContainer $jobDefinitionsContainer) {
        $this->queueManager = $queueManager;
        $this->jobDefinitionsContainer = $jobDefinitionsContainer;
    }
    

    protected function push(string $jobUuid): void
    {
        $jobDefinition = $this->jobDefinitionsContainer->get(ExampleJob::JOB_NAME);

        $exampleJob = new ExampleJob(
            $jobUuid,
            new DateTimeImmutable(),
            JobInterface::INIT_ATTEMPTS,
            $jobDefinition,
            new ArrayCollection([ExampleJob::PARAMETER_FOO => 'bar'])
        );

        $this->queueManager->push($exampleJob);
    }
}

```

### 5. Run worker
```php
<?php declare(strict_types = 1);

namespace BE\AdapterSdk\Console\Commands\Queue;

use BE\QueueManagement\Queue\RabbitMQ\RabbitMQQueueManager;
use BE\QueueManagement\Queue\WorkerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class WorkerStartCommand extends Command
{
    /**
     * @var WorkerInterface
     */
    private $worker;


    public function __construct(WorkerInterface $worker)
    {
        parent::__construct();
        $this->worker = $worker;
    }


    protected function configure(): void
    {
        $this->setName('queue:worker:start');
        $this->setDescription('Start queue worker');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->worker->start(
            'example_queue',
            [
                RabbitMQQueueManager::PREFETCH_COUNT => 1,
                RabbitMQQueueManager::NO_ACK => true,
            ]
        );
        
        $output->writeln('Worker started');

        return 0;
    }
}

```
