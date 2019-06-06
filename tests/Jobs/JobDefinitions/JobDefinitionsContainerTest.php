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
use PHPUnit\Framework\TestCase;
use Tests\BE\QueueManagement\Jobs\Execution\DummyJobProcessor;

class JobDefinitionsContainerTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    private const SIMPLE_JOB_NAME = 'simpleJob';


    public function testGetJobDefinition(): void
    {
        $dummyJobProcessor = new DummyJobProcessor();
        $simpleJobLoader = new SimpleJobLoader();
        $constantDelayRule = new ConstantDelayRule(10);

        $jobDefinitionsConfig = [
            self::SIMPLE_JOB_NAME => [
                JobDefinitionFactoryInterface::JOB_CLASS      => SimpleJob::class,
                JobDefinitionFactoryInterface::QUEUE_NAME     => DummyJobDefinition::QUEUE_NAME,
                JobDefinitionFactoryInterface::MAX_ATTEMPTS   => null,
                JobDefinitionFactoryInterface::JOB_PROCESSOR  => $dummyJobProcessor,
                JobDefinitionFactoryInterface::JOB_LOADER     => $simpleJobLoader,
                JobDefinitionFactoryInterface::JOB_DELAY_RULE => $constantDelayRule,
            ],
        ];
        $jobDefinitionContainer = $this->createJobDefinitionsContainer($jobDefinitionsConfig);

        $simpleJobDefinition = $jobDefinitionContainer->get(self::SIMPLE_JOB_NAME);

        self::assertTrue($jobDefinitionContainer->has(self::SIMPLE_JOB_NAME));
        self::assertFalse($jobDefinitionContainer->has('unknownJobName'));
        self::assertNull($simpleJobDefinition->getMaxAttempts());
        self::assertEquals(DummyJobDefinition::QUEUE_NAME, $simpleJobDefinition->getQueueName());
        self::assertEquals(DummyJobDefinition::QUEUE_NAME, $simpleJobDefinition->getQueueName());
        self::assertEquals($dummyJobProcessor, $simpleJobDefinition->getJobProcessor());
        self::assertEquals($simpleJobLoader, $simpleJobDefinition->getJobLoader());
        self::assertEquals($constantDelayRule, $simpleJobDefinition->getDelayRule());
    }


    public function testGetUnknownJobDefinition(): void
    {
        $jobDefinitionContainer = $this->createJobDefinitionsContainer([]);

        $this->expectException(UnknownJobDefinitionException::class);
        $this->expectExceptionMessage('Job definition (simpleJob) not found, maybe you forget to register it');

        $jobDefinitionContainer->get(self::SIMPLE_JOB_NAME);
    }


    private function createJobDefinitionsContainer(array $jobDefinitionsConfig): JobDefinitionsContainer
    {
        $jobDefinitionFactory = new JobDefinitionFactory(new SimpleJobLoader());

        return new JobDefinitionsContainer($jobDefinitionsConfig, $jobDefinitionFactory);
    }
}
