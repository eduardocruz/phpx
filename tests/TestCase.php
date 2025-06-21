<?php

declare(strict_types=1);

namespace PHPX\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Get the path to the project root directory.
     */
    protected function getProjectRoot(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Get the path to the src directory.
     */
    protected function getSrcPath(): string
    {
        return $this->getProjectRoot() . '/src';
    }

    /**
     * Get the path to the bin directory.
     */
    protected function getBinPath(): string
    {
        return $this->getProjectRoot() . '/bin';
    }

    /**
     * Get the path to the vendor directory.
     */
    protected function getVendorPath(): string
    {
        return $this->getProjectRoot() . '/vendor';
    }
}
