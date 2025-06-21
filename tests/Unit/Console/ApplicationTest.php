<?php

declare(strict_types=1);

namespace PHPX\Tests\Unit\Console;

use PHPX\Console\Application;
use PHPX\Tests\TestCase;

class ApplicationTest extends TestCase
{
    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->application = new Application();
    }

    public function testApplicationCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Application::class, $this->application);
    }

    public function testApplicationHasCorrectName(): void
    {
        $this->assertSame('PHPX', $this->application->getName());
    }

    public function testApplicationHasVersion(): void
    {
        $version = $this->application->getVersion();
        $this->assertIsString($version);
        $this->assertNotEmpty($version);
    }

    public function testApplicationIsConsoleApplication(): void
    {
        $this->assertInstanceOf(\Symfony\Component\Console\Application::class, $this->application);
    }
}
