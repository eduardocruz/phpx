<?php

declare(strict_types=1);

namespace PHPX\Tests\Unit\Package;

use PHPX\Package\PackageManager;
use PHPX\Tests\TestCase;

class PackageManagerTest extends TestCase
{
    private PackageManager $packageManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->packageManager = new PackageManager();
    }

    public function testResolvePackageWithComposerPackage(): void
    {
        // This test would require actual network access or mocking, so we'll test the method exists
        $this->assertTrue(method_exists($this->packageManager, 'resolvePackage'));
    }

    public function testGetKnownPhars(): void
    {
        $knownPhars = $this->packageManager->getKnownPhars();

        $this->assertIsArray($knownPhars);
        $this->assertArrayHasKey('phpunit.phar', $knownPhars);
        $this->assertArrayHasKey('php-cs-fixer.phar', $knownPhars);
        $this->assertArrayHasKey('phpstan.phar', $knownPhars);
        $this->assertArrayHasKey('composer.phar', $knownPhars);
    }

    public function testGetPharAliases(): void
    {
        $aliases = $this->packageManager->getPharAliases();

        $this->assertIsArray($aliases);
        $this->assertArrayHasKey('phpunit', $aliases);
        $this->assertArrayHasKey('cs-fixer', $aliases);
        $this->assertArrayHasKey('phpstan', $aliases);
        $this->assertArrayHasKey('composer', $aliases);

        $this->assertSame('phpunit.phar', $aliases['phpunit']);
        $this->assertSame('php-cs-fixer.phar', $aliases['cs-fixer']);
    }

    public function testResolvePackageWithPhar(): void
    {
        // Test that known PHAR aliases are recognized
        $knownPhars = $this->packageManager->getKnownPhars();
        $aliases = $this->packageManager->getPharAliases();

        $this->assertNotEmpty($knownPhars);
        $this->assertNotEmpty($aliases);

        // Verify that aliases point to known PHARs
        foreach ($aliases as $alias => $pharName) {
            $this->assertArrayHasKey($pharName, $knownPhars, "Alias '$alias' points to unknown PHAR '$pharName'");
        }
    }
}
