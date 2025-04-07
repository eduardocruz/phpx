<?php

declare(strict_types=1);

namespace PHPX\Package;

use Symfony\Component\Filesystem\Filesystem;

class PackageManager
{
    private string $cacheDir;
    private Filesystem $filesystem;
    private bool $debug;

    public function __construct(bool $debug = false)
    {
        $this->cacheDir = $this->getCacheDir();
        $this->filesystem = new Filesystem();
        $this->debug = $debug;
        
        if (!$this->filesystem->exists($this->cacheDir)) {
            $this->filesystem->mkdir($this->cacheDir);
        }
    }

    public function resolvePackage(string $packageSpec): Package
    {
        // Handle PHAR files
        if ($this->isPhar($packageSpec)) {
            return $this->handlePhar($packageSpec);
        }

        // Parse package spec (name and version)
        list($name, $version) = $this->parsePackageSpec($packageSpec);
        
        // Check if package is already cached
        $packageDir = $this->getCachePathForPackage($name, $version);
        
        if ($this->debug) {
            echo "Package cache path: $packageDir\n";
        }
        
        if (!$this->filesystem->exists($packageDir)) {
            if ($this->debug) {
                echo "Package not found in cache, installing...\n";
            }
            
            $this->installPackage($name, $version, $packageDir);
        } else if ($this->debug) {
            echo "Package found in cache\n";
        }

        return new Package($packageDir);
    }

    private function isPhar(string $packageSpec): bool
    {
        return str_ends_with(strtolower($packageSpec), '.phar');
    }

    private function handlePhar(string $pharPath): Package
    {
        if (!file_exists($pharPath)) {
            throw new \RuntimeException("PHAR file not found: $pharPath");
        }

        $pharDir = $this->cacheDir . '/phars/' . basename($pharPath, '.phar');
        if (!$this->filesystem->exists($pharDir)) {
            $this->filesystem->mkdir($pharDir);
            copy($pharPath, $pharDir . '/' . basename($pharPath));
        }

        return new Package($pharDir, true);
    }

    private function parsePackageSpec(string $spec): array
    {
        $parts = explode(':', $spec);
        return [
            $parts[0],
            $parts[1] ?? null
        ];
    }

    private function installPackage(string $name, ?string $version, string $targetDir): void
    {
        // Create temporary composer.json
        $tempDir = sys_get_temp_dir() . '/phpx_' . uniqid();
        $this->filesystem->mkdir($tempDir);

        $composerJson = [
            'require' => [
                $name => $version ?? '*'
            ]
        ];

        file_put_contents($tempDir . '/composer.json', json_encode($composerJson));

        if ($this->debug) {
            echo "Created temporary composer.json in $tempDir\n";
        }

        // Run composer install in temp directory
        $command = sprintf(
            'cd %s && composer install --no-dev --no-interaction 2>&1',
            escapeshellarg($tempDir)
        );
        
        if ($this->debug) {
            echo "Running: $command\n";
        }
        
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->filesystem->remove($tempDir);
            throw new \RuntimeException("Failed to install package: " . implode("\n", $output));
        }

        // Move installed package to cache
        $vendorPackageDir = $tempDir . '/vendor/' . $name;
        
        if (!is_dir($vendorPackageDir)) {
            // Try to find the actual package directory
            if ($this->debug) {
                echo "Package directory not found at expected path, searching...\n";
                echo "Contents of vendor directory:\n";
                system("ls -la $tempDir/vendor");
            }
            
            $this->filesystem->remove($tempDir);
            throw new \RuntimeException("Package directory not found after installation");
        }

        // Also copy vendor directory to ensure dependencies are available
        $this->filesystem->mirror($vendorPackageDir, $targetDir);
        $this->filesystem->mirror($tempDir . '/vendor', $targetDir . '/vendor');
        
        if ($this->debug) {
            echo "Copied package to cache: $targetDir\n";
        }
        
        $this->filesystem->remove($tempDir);
    }

    private function getCachePathForPackage(string $name, ?string $version): string
    {
        return $this->cacheDir . '/' . str_replace('/', '_', $name) . 
               ($version ? '_' . $version : '');
    }

    private function getCacheDir(): string
    {
        $baseDir = getenv('XDG_CACHE_HOME') 
            ?: (getenv('HOME') . '/.cache');
        
        return $baseDir . '/phpx';
    }
}