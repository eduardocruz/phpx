<?php

declare(strict_types=1);

namespace PHPX\Package;

use Composer\Factory;
use Composer\IO\NullIO;
use Symfony\Component\Filesystem\Filesystem;

class PackageManager
{
    private string $cacheDir;
    private Filesystem $filesystem;

    public function __construct()
    {
        $this->cacheDir = $this->getCacheDir();
        $this->filesystem = new Filesystem();
        
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
        if (!$this->filesystem->exists($packageDir)) {
            $this->installPackage($name, $version, $packageDir);
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
        $composer = Factory::create(new NullIO());
        $package = $composer->getRepositoryManager()
            ->findPackage($name, $version ?? '*');

        if (!$package) {
            throw new \RuntimeException("Package not found: $name" . ($version ? ":$version" : ''));
        }

        // Create temporary composer.json
        $tempDir = sys_get_temp_dir() . '/phpx_' . uniqid();
        $this->filesystem->mkdir($tempDir);

        file_put_contents($tempDir . '/composer.json', json_encode([
            'require' => [
                $name => $version ?? '*'
            ]
        ]));

        // Install package
        $composer = Factory::create(new NullIO(), $tempDir . '/composer.json');
        $composer->install();

        // Move to cache
        $this->filesystem->mirror($tempDir . '/vendor/' . $name, $targetDir);
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