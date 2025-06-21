<?php

declare(strict_types=1);

namespace PHPX\Tests\Unit\Package;

use PHPX\Package\Package;
use PHPX\Tests\TestCase;

class PackageTest extends TestCase
{
    public function testPackageCreationWithoutPhar(): void
    {
        $package = new Package('/path/to/package', false);

        $this->assertSame('/path/to/package', $package->getPath());
        $this->assertFalse($package->isPhar());
    }

    public function testPackageCreationWithPhar(): void
    {
        $package = new Package('/path/to/phar', true);

        $this->assertSame('/path/to/phar', $package->getPath());
        $this->assertTrue($package->isPhar());
    }

    public function testGetComposerJson(): void
    {
        // Create a temporary directory with composer.json
        $tempDir = sys_get_temp_dir() . '/phpx_test_' . uniqid();
        mkdir($tempDir);

        $composerContent = [
            'name' => 'test/package',
            'version' => '1.0.0',
        ];
        file_put_contents($tempDir . '/composer.json', json_encode($composerContent));

        $package = new Package($tempDir, false);
        $composerJson = $package->getComposerJson();

        $this->assertIsArray($composerJson);
        $this->assertArrayHasKey('name', $composerJson);
        $this->assertSame('test/package', $composerJson['name']);

        // Cleanup
        unlink($tempDir . '/composer.json');
        rmdir($tempDir);
    }

    public function testGetComposerJsonNotExists(): void
    {
        $package = new Package('/nonexistent/path', false);
        $composerJson = $package->getComposerJson();

        $this->assertIsArray($composerJson);
        $this->assertEmpty($composerJson);
    }
}
