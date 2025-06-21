<?php

declare(strict_types=1);

namespace PHPX\Package;

class Package
{
    private string $path;
    private bool $isPhar;

    public function __construct(string $path, bool $isPhar = false)
    {
        $this->path = $path;
        $this->isPhar = $isPhar;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isPhar(): bool
    {
        return $this->isPhar;
    }

    public function getExecutable(): string
    {
        if ($this->isPhar) {
            $phars = glob($this->path . '/*.phar');

            if (empty($phars)) {
                throw new \RuntimeException('No PHAR file found in package');
            }

            return $phars[0];
        }

        // Check bin directory first
        $binDir = $this->path . '/vendor/bin';

        if (is_dir($binDir)) {
            $packageName = basename(str_replace('_', '/', basename($this->path)));

            // Common bin patterns
            $binPatterns = [
                // Exact package name
                $binDir . '/' . $packageName,
                // With phpunit-specific name
                $binDir . '/phpunit',
                // Any file
                $binDir . '/*',
            ];

            foreach ($binPatterns as $pattern) {
                $matches = glob($pattern);

                if (!empty($matches)) {
                    foreach ($matches as $match) {
                        if (is_file($match) && is_executable($match)) {
                            return $match;
                        }
                    }
                }
            }
        }

        // Check composer.json bin
        $composerJson = $this->getComposerJson();

        if (isset($composerJson['bin'])) {
            $bin = is_array($composerJson['bin'])
                ? $composerJson['bin'][0]
                : $composerJson['bin'];

            $binPath = $this->path . '/' . $bin;

            if (file_exists($binPath)) {
                return $binPath;
            }
        }

        // Look for a PHP file directly in the package
        $phpFiles = glob($this->path . '/*.php');

        if (!empty($phpFiles)) {
            return $phpFiles[0];
        }

        throw new \RuntimeException('No executable found in package');
    }

    public function getComposerJson(): array
    {
        $composerJsonPath = $this->path . '/composer.json';

        if (!file_exists($composerJsonPath)) {
            return [];
        }

        $content = file_get_contents($composerJsonPath);

        if ($content === false) {
            return [];
        }

        return json_decode($content, true) ?? [];
    }
}
