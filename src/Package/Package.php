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
            return glob($this->path . '/*.phar')[0] ?? '';
        }

        $composerJson = $this->getComposerJson();
        if (isset($composerJson['bin'])) {
            $bin = is_array($composerJson['bin']) 
                ? $composerJson['bin'][0] 
                : $composerJson['bin'];
            
            return $this->path . '/' . $bin;
        }

        throw new \RuntimeException('No executable found in package');
    }

    public function getComposerJson(): array
    {
        $composerJsonPath = $this->path . '/composer.json';
        if (!file_exists($composerJsonPath)) {
            return [];
        }

        return json_decode(file_get_contents($composerJsonPath), true) ?? [];
    }
}