<?php

declare(strict_types=1);

namespace PHPX\Package;

use Symfony\Component\Process\Process;

class ExecutionEnvironment
{
    private Package $package;

    public function __construct(Package $package)
    {
        $this->package = $package;
    }

    public function execute(array $args = []): int
    {
        $executable = $this->package->getExecutable();
        
        if ($this->package->isPhar()) {
            array_unshift($args, $executable);
            $executable = 'php';
        }

        $process = new Process(
            array_merge([$executable], $args),
            $this->package->getPath(),
            $this->getEnvironment()
        );

        $process->setTty(true);
        $process->run();

        return $process->getExitCode();
    }

    private function getEnvironment(): array
    {
        return array_merge($_ENV, [
            'PHPX_PACKAGE_PATH' => $this->package->getPath(),
        ]);
    }
}