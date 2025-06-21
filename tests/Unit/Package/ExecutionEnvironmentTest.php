<?php

declare(strict_types=1);

namespace PHPX\Tests\Unit\Package;

use PHPX\Package\ExecutionEnvironment;
use PHPX\Package\Package;
use PHPX\Tests\TestCase;

class ExecutionEnvironmentTest extends TestCase
{
    private ExecutionEnvironment $environment;
    private Package $testPackage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testPackage = new Package('/tmp/test-package', false);
        $this->environment = new ExecutionEnvironment($this->testPackage);
    }

    public function testExecutionEnvironmentCreation(): void
    {
        $this->assertInstanceOf(ExecutionEnvironment::class, $this->environment);
    }

    public function testExecutionEnvironmentCreationWithDebug(): void
    {
        $debugEnvironment = new ExecutionEnvironment($this->testPackage, true);
        $this->assertInstanceOf(ExecutionEnvironment::class, $debugEnvironment);
    }

    public function testExecuteMethodExists(): void
    {
        $this->assertTrue(method_exists($this->environment, 'execute'));
        $this->assertTrue(is_callable([$this->environment, 'execute']));
    }

    public function testExecuteWithEmptyArgs(): void
    {
        // We can't actually execute because the test package doesn't exist
        // But we can test that the method signature is correct
        $reflection = new \ReflectionMethod($this->environment, 'execute');
        $parameters = $reflection->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertSame('args', $parameters[0]->getName());
        $this->assertTrue($parameters[0]->hasType());
        $this->assertSame('array', $parameters[0]->getType()->getName());
    }

    public function testExecuteReturnsInteger(): void
    {
        $reflection = new \ReflectionMethod($this->environment, 'execute');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame('int', $returnType->getName());
    }

}
