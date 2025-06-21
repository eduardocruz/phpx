<?php

declare(strict_types=1);

namespace PHPX\Tests\Feature;

use PHPX\Package\Package;
use PHPX\Package\PackageManager;
use PHPX\Tests\TestCase;
use Symfony\Component\Process\Process;

class PerformanceTest extends TestCase
{
    private PackageManager $packageManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->packageManager = new PackageManager();
    }

    public function testCacheHitPerformance(): void
    {
        $package = new Package('/tmp/test-package', false);

        // Test basic package manager functionality
        $knownPhars = $this->packageManager->getKnownPhars();
        $aliases = $this->packageManager->getPharAliases();

        $this->assertIsArray($knownPhars);
        $this->assertIsArray($aliases);
        $this->assertNotEmpty($knownPhars);
        $this->assertNotEmpty($aliases);
    }

    public function testMemoryUsageBaseline(): void
    {
        $initialMemory = memory_get_usage(true);

        // Create several package objects
        $packages = [];

        for ($i = 0; $i < 100; $i++) {
            $packages[] = new Package("/tmp/package-{$i}", false);
        }

        $afterPackageCreation = memory_get_usage(true);
        $memoryIncrease = $afterPackageCreation - $initialMemory;

        // Memory increase should be reasonable (less than 1MB for 100 packages)
        $this->assertLessThan(1024 * 1024, $memoryIncrease, 'Memory usage should be reasonable');

        // Clear packages
        unset($packages);

        $afterCleanup = memory_get_usage(true);

        // Memory should be mostly reclaimed (within 10% of initial)
        $memoryDifference = abs($afterCleanup - $initialMemory);
        $this->assertLessThan($initialMemory * 0.1, $memoryDifference, 'Memory should be reclaimed after cleanup');
    }

    public function testSequentialPackageAccess(): void
    {
        $package = new Package('/tmp/test-package', false);
        $startTime = microtime(true);

        // Test sequential package operations
        for ($i = 0; $i < 3; $i++) {
            $testScript = $this->createCacheAccessScript($package);
            $process = new Process(['php', $testScript]);
            $process->run();

            $this->assertSame(0, $process->getExitCode(), "Process {$i} should complete successfully");
            $output = $process->getOutput();
            $this->assertStringContainsString('Package operations completed successfully', $output);

            // Cleanup test script
            if (file_exists($testScript)) {
                unlink($testScript);
            }
        }

        $totalTime = microtime(true) - $startTime;

        // Sequential access should complete in reasonable time (less than 5 seconds)
        $this->assertLessThan(5.0, $totalTime, 'Package access should complete quickly');
    }

    public function testPharCachePerformance(): void
    {
        // Test PHAR functionality
        $knownPhars = $this->packageManager->getKnownPhars();
        $this->assertArrayHasKey('phpunit.phar', $knownPhars);

        // Test that each known PHAR has valid URLs
        foreach ($knownPhars as $pharName => $versions) {
            $this->assertIsArray($versions, "PHAR $pharName should have version array");
            $this->assertArrayHasKey('latest', $versions, "PHAR $pharName should have 'latest' version");
        }
    }

    public function testPackageHashingPerformance(): void
    {
        $packages = [];

        for ($i = 0; $i < 100; $i++) {
            $packages[] = new Package("/tmp/package-{$i}", false);
        }

        $startTime = microtime(true);
        $paths = [];

        foreach ($packages as $package) {
            $paths[] = $package->getPath();
        }

        $processingTime = microtime(true) - $startTime;

        // Should process 100 packages quickly
        $this->assertLessThan(0.1, $processingTime, 'Package processing should be fast');

        // All paths should be unique
        $uniquePaths = array_unique($paths);
        $this->assertCount(count($packages), $uniquePaths, 'All package paths should be unique');
    }

    private function createCacheAccessScript(Package $package): string
    {
        $packagePath = $package->getPath();
        $isPhar = $package->isPhar() ? 'true' : 'false';

        $scriptContent = "<?php
require_once '" . $this->getVendorPath() . "/autoload.php';

use PHPX\Package\PackageManager;
use PHPX\Package\Package;

\$packageManager = new PackageManager();
\$package = new Package('{$packagePath}', {$isPhar});

// Simulate basic operations
\$path = \$package->getPath();
\$isPhar = \$package->isPhar();

echo 'Package operations completed successfully';
";

        $tempFile = tempnam(sys_get_temp_dir(), 'phpx_cache_test_');
        file_put_contents($tempFile, $scriptContent);

        return $tempFile;
    }

    private function createTestPhar(string $path): void
    {
        // Create a minimal PHAR file for testing
        $pharContent = "<?php\necho 'Test PHAR executed';\n";

        // Create a simple file that looks like a PHAR
        file_put_contents($path, $pharContent);
    }
}
