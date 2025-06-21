<?php

declare(strict_types=1);

namespace PHPX\Package;

use Symfony\Component\Filesystem\Filesystem;

class PackageManager
{
    private string $cacheDir;
    private Filesystem $filesystem;
    private bool $debug;

    private array $knownPhars = [
        'php-cs-fixer.phar' => [
            'latest' => 'https://cs.symfony.com/download/php-cs-fixer-v3.phar',
            'v3' => 'https://cs.symfony.com/download/php-cs-fixer-v3.phar',
            '3.26' => 'https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/releases/download/v3.26.0/php-cs-fixer.phar',
            '3.25' => 'https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/releases/download/v3.25.1/php-cs-fixer.phar',
            '3.24' => 'https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/releases/download/v3.24.0/php-cs-fixer.phar',
        ],
        'phpunit.phar' => [
            'latest' => 'https://phar.phpunit.de/phpunit-latest.phar',
            '10' => 'https://phar.phpunit.de/phpunit-10.phar',
            '9' => 'https://phar.phpunit.de/phpunit-9.phar',
            '8' => 'https://phar.phpunit.de/phpunit-8.phar',
        ],
        'composer.phar' => [
            'latest' => 'https://getcomposer.org/composer.phar',
            '2' => 'https://getcomposer.org/composer-2.phar',
            '1' => 'https://getcomposer.org/composer-1.phar',
        ],
        'phpstan.phar' => [
            'latest' => 'https://github.com/phpstan/phpstan/releases/latest/download/phpstan.phar',
        ],
    ];

    private array $pharAliases = [
        'cs-fixer' => 'php-cs-fixer.phar',
        'phpunit' => 'phpunit.phar',
        'phpstan' => 'phpstan.phar',
        'composer' => 'composer.phar',
    ];

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
        // Handle direct PHP files
        if ($this->isDirectPhpFile($packageSpec)) {
            return $this->handleDirectPhpFile($packageSpec);
        }

        // Handle PHAR files
        if ($this->isPhar($packageSpec)) {
            return $this->handlePhar($packageSpec);
        }

        // Parse package spec (name and version)
        [$name, $version] = $this->parsePackageSpec($packageSpec);

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
        } elseif ($this->debug) {
            echo "Package found in cache\n";
        }

        return new Package($packageDir);
    }

    private function resolveAlias(string $name): string
    {
        // If it's already a .phar file, return as is
        if (str_ends_with(strtolower($name), '.phar')) {
            return $name;
        }

        // Check if it's an alias
        if (isset($this->pharAliases[$name])) {
            return $this->pharAliases[$name];
        }

        // Check if it's an alias without .phar
        $nameWithPhar = $name . '.phar';

        if (isset($this->knownPhars[$nameWithPhar])) {
            return $nameWithPhar;
        }

        return $name;
    }

    private function isDirectPhpFile(string $packageSpec): bool
    {
        // Check if it's a direct PHP file path (absolute or relative)
        $parts = explode(':', $packageSpec);
        $path = $parts[0];
        
        // Check if it's a PHP file and exists
        return (str_ends_with(strtolower($path), '.php') && file_exists($path)) ||
               (is_file($path) && $this->isPhpFile($path));
    }

    private function isPhpFile(string $path): bool
    {
        // Check if file starts with PHP shebang or opening tag
        if (!is_readable($path)) {
            return false;
        }
        
        $handle = fopen($path, 'r');
        if (!$handle) {
            return false;
        }
        
        $firstLine = fgets($handle);
        fclose($handle);
        
        return $firstLine !== false && (
            str_starts_with($firstLine, '#!/usr/bin/env php') ||
            str_starts_with($firstLine, '#!/usr/bin/php') ||
            str_starts_with($firstLine, '<?php')
        );
    }

    private function handleDirectPhpFile(string $filePath): Package
    {
        // Extract just the file path (ignore version spec if any)
        $parts = explode(':', $filePath);
        $actualPath = $parts[0];
        
        if (!file_exists($actualPath)) {
            throw new \RuntimeException("PHP file not found: $actualPath");
        }
        
        if (!is_readable($actualPath)) {
            throw new \RuntimeException("PHP file is not readable: $actualPath");
        }
        
        if ($this->debug) {
            echo "Handling direct PHP file: $actualPath\n";
        }
        
        // Create a temporary package structure that points to the file's directory
        $fileDir = dirname(realpath($actualPath));
        $fileName = basename($actualPath);
        
        // Create a Package that represents the direct file execution
        return new Package($fileDir, false, $fileName);
    }

    private function isPhar(string $packageSpec): bool
    {
        // Split by version separator and check the base name
        $parts = explode(':', $packageSpec);
        $name = $this->resolveAlias($parts[0]);

        return str_ends_with(strtolower($name), '.phar');
    }

    private function handlePhar(string $pharPath): Package
    {
        // Parse PHAR name and version
        $parts = explode(':', $pharPath);
        $pharName = $this->resolveAlias(basename($parts[0]));
        $requestedVersion = $parts[1] ?? 'latest';

        // Get base directory for PHAR cache
        $pharBaseDir = $this->cacheDir . '/phars/' . basename($pharName, '.phar');
        $pharVersionDir = $pharBaseDir . '/' . $requestedVersion;

        // If it's a known PHAR and file doesn't exist locally, try to download it
        if (!file_exists($pharPath) && isset($this->knownPhars[$pharName])) {
            if (!isset($this->knownPhars[$pharName][$requestedVersion])) {
                throw new \RuntimeException("Version '$requestedVersion' not found for PHAR '$pharName'");
            }

            $downloadUrl = $this->knownPhars[$pharName][$requestedVersion];

            if ($this->debug) {
                echo "PHAR not found locally, downloading version $requestedVersion from $downloadUrl\n";
            }

            if (!$this->filesystem->exists($pharVersionDir)) {
                $this->filesystem->mkdir($pharVersionDir);
            }

            $downloadedPhar = $pharVersionDir . '/' . $pharName;
            $success = @file_get_contents($downloadUrl);

            if ($success === false) {
                throw new \RuntimeException("Failed to download PHAR from $downloadUrl");
            }

            file_put_contents($downloadedPhar, $success);
            chmod($downloadedPhar, 0o755); // Make executable

            if ($this->debug) {
                echo "Successfully downloaded PHAR version $requestedVersion to $downloadedPhar\n";
            }

            return new Package($pharVersionDir, true);
        }

        // Original local file handling
        if (!file_exists($pharPath)) {
            throw new \RuntimeException("PHAR file not found: $pharPath and not recognized as a known PHAR");
        }

        if (!$this->filesystem->exists($pharVersionDir)) {
            $this->filesystem->mkdir($pharVersionDir);
            copy($pharPath, $pharVersionDir . '/' . $pharName);
        }

        return new Package($pharVersionDir, true);
    }

    private function parsePackageSpec(string $spec): array
    {
        $parts = explode(':', $spec);

        return [
            $parts[0],
            $parts[1] ?? null,
        ];
    }

    private function installPackage(string $name, ?string $version, string $targetDir): void
    {
        // Create temporary composer.json
        $tempDir = sys_get_temp_dir() . '/phpx_' . uniqid();
        $this->filesystem->mkdir($tempDir);

        $composerJson = [
            'require' => [
                $name => $version ?? '*',
            ],
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

            throw new \RuntimeException('Failed to install package: ' . implode("\n", $output));
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

            throw new \RuntimeException('Package directory not found after installation');
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

    /**
     * Get the cache directory path for PHPX.
     *
     * Uses ~/.phpx/cache instead of ~/.cache/phpx to:
     * 1. Maintain isolation from globally installed packages
     * 2. Avoid conflicts with system-wide PHARs and Composer packages
     * 3. Make it clear these PHARs are managed by PHPX specifically
     * 4. Simplify cleanup and version management
     *
     * This aligns with PHPX's goal of providing non-conflicting PHAR management
     * while maintaining clear separation from global package installations.
     */
    private function getCacheDir(): string
    {
        $homeDir = getenv('HOME') ?: getenv('USERPROFILE');

        return $homeDir . '/.phpx/cache';
    }

    public function getKnownPhars(): array
    {
        return $this->knownPhars;
    }

    public function getPharAliases(): array
    {
        return $this->pharAliases;
    }
}
