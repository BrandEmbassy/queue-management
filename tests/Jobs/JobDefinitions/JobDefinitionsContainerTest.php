<?php declare(strict_types = 1);

namespace Tests\BE\QueueManagement\Jobs\JobDefinitions;

use BE\QueueManagement\Jobs\FailResolving\DelayRules\ConstantDelayRule;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionFactory;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionFactoryInterface;
use BE\QueueManagement\Jobs\JobDefinitions\JobDefinitionsContainer;
use BE\QueueManagement\Jobs\JobDefinitions\UnknownJobDefinitionException;
use BE\QueueManagement\Jobs\Loading\SimpleJobLoader;
use BE\QueueManagement\Jobs\SimpleJob;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Tests\BE\QueueManagement\Jobs\Execution\ExampleJobProcessor;

/**
 * @final
 */
class JobDefinitionsContainerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const SIMPLE_JOB_NAME = 'simpleJob';


    public function testGetJobDefinition(): void
    {
        $exampleJobProcessor = new ExampleJobProcessor();
        $simpleJobLoader = new SimpleJobLoader();
        $constantDelayRule = new ConstantDelayRule(10);

        $jobDefinitionsConfig = [
            self::SIMPLE_JOB_NAME => [
                JobDefinitionFactoryInterface::JOB_CLASS => SimpleJob::class,
                JobDefinitionFactoryInterface::QUEUE_NAME => ExampleJobDefinition::QUEUE_NAME,
                JobDefinitionFactoryInterface::MAX_ATTEMPTS => null,
                JobDefinitionFactoryInterface::JOB_PROCESSOR => $exampleJobProcessor,
                JobDefinitionFactoryInterface::JOB_LOADER => $simpleJobLoader,
                JobDefinitionFactoryInterface::JOB_DELAY_RULE => $constantDelayRule,
            ],
        ];
        $jobDefinitionContainer = $this->createJobDefinitionsContainer($jobDefinitionsConfig);

        $simpleJobDefinition = $jobDefinitionContainer->get(self::SIMPLE_JOB_NAME);

        Assert::assertTrue($jobDefinitionContainer->has(self::SIMPLE_JOB_NAME));
        Assert::assertFalse($jobDefinitionContainer->has('unknownJobName'));
        Assert::assertNull($simpleJobDefinition->getMaxAttempts());
        Assert::assertSame(ExampleJobDefinition::QUEUE_NAME, $simpleJobDefinition->getQueueName());
        Assert::assertSame(ExampleJobDefinition::QUEUE_NAME, $simpleJobDefinition->getQueueName());
        Assert::assertSame($exampleJobProcessor, $simpleJobDefinition->getJobProcessor());
        Assert::assertSame($simpleJobLoader, $simpleJobDefinition->getJobLoader());
        Assert::assertSame($constantDelayRule, $simpleJobDefinition->getDelayRule());
    }


    public function testGetAllJobDefinitions(): void
    {
        $exampleJobProcessor = new ExampleJobProcessor();
        $simpleJobLoader = new SimpleJobLoader();
        $constantDelayRule = new ConstantDelayRule(10);

        $jobDefinitionsConfig = [
            self::SIMPLE_JOB_NAME => [
                JobDefinitionFactoryInterface::JOB_CLASS => SimpleJob::class,
                JobDefinitionFactoryInterface::QUEUE_NAME => ExampleJobDefinition::QUEUE_NAME,
                JobDefinitionFactoryInterface::MAX_ATTEMPTS => null,
                JobDefinitionFactoryInterface::JOB_PROCESSOR => $exampleJobProcessor,
                JobDefinitionFactoryInterface::JOB_LOADER => $simpleJobLoader,
                JobDefinitionFactoryInterface::JOB_DELAY_RULE => $constantDelayRule,
            ],
        ];
        $jobDefinitionContainer = $this->createJobDefinitionsContainer($jobDefinitionsConfig);

        Assert::assertCount(1, $jobDefinitionContainer->all());
    }


    public function testGetUnknownJobDefinition(): void
    {
        $jobDefinitionContainer = $this->createJobDefinitionsContainer([]);

        $this->expectException(UnknownJobDefinitionException::class);
        $this->expectExceptionMessage('Job definition (simpleJob) not found, maybe you forget to register it');

        $jobDefinitionContainer->get(self::SIMPLE_JOB_NAME);
    }


    /**
     * @param mixed[] $jobDefinitionsConfig
     */
    private function createJobDefinitionsContainer(array $jobDefinitionsConfig): JobDefinitionsContainer
    {
        $jobDefinitionFactory = new JobDefinitionFactory(new SimpleJobLoader());

        return new JobDefinitionsContainer($jobDefinitionsConfig, $jobDefinitionFactory);
    }
}
