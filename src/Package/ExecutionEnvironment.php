<?php

declare(strict_types=1);

namespace PHPX\Package;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ExecutionEnvironment
{
    private Package $package;
    private bool $debug;
    private string $originalWorkingDir;

    public function __construct(Package $package, bool $debug = false)
    {
        $this->package = $package;
        $this->debug = $debug;
        $this->originalWorkingDir = getcwd() ?: '.';
    }

    public function execute(array $args = []): int
    {
        $executable = $this->package->getExecutable();

        if ($this->debug) {
            echo "Executable: $executable\n";
            echo "Arguments: " . implode(' ', $args) . "\n";
            echo "Original working directory: {$this->originalWorkingDir}\n";
        }

        // Prepare command
        $command = [];

        // If it's a PHP file or PHAR, run with PHP
        if ($this->package->isPhar() || str_ends_with($executable, '.php')) {
            $command[] = PHP_BINARY;
            $command[] = $executable;
        } else {
            $command[] = $executable;
        }

        // Add arguments
        foreach ($args as $arg) {
            $command[] = $arg;
        }

        if ($this->debug) {
            echo "Full command: " . implode(' ', $command) . "\n";
        }

        // Create and configure process
        // CRITICAL FIX: Use the original working directory instead of the package path
        $process = new Process(
            $command,
            $this->originalWorkingDir, // Changed from $this->package->getPath() to use the original dir
            $this->getEnvironment()
        );

        $process->setTimeout(null);
        $process->setTty(false); // Disable TTY mode for better compatibility

        try {
            // Run the process and output directly
            $process->mustRun(function ($type, $buffer) {
                echo $buffer;
            });

            return $process->getExitCode();
        } catch (ProcessFailedException $e) {
            if ($this->debug) {
                echo "Process failed: " . $e->getMessage() . "\n";
            }
            return $e->getProcess()->getExitCode();
        }
    }

    private function getEnvironment(): array
    {
        // Add package bin directory to the PATH
        $path = $this->package->getPath() . '/vendor/bin:' . getenv('PATH');

        return array_merge($_SERVER, [
            'PHPX_PACKAGE_PATH' => $this->package->getPath(),
            'PATH' => $path,
            'COMPOSER_VENDOR_DIR' => $this->package->getPath() . '/vendor',
            'PWD' => $this->originalWorkingDir, // Ensure PWD is set to the original directory
        ]);
    }
}
