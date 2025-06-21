<?php

declare(strict_types=1);

namespace PHPX\Package;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

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
            echo 'Arguments: ' . implode(' ', $args) . "\n";
            echo "Original working directory: {$this->originalWorkingDir}\n";
        }

        // Prepare command
        $command = [];

        // If it's a PHP file or PHAR, run with PHP
        if ($this->package->isPhar() || str_ends_with($executable, '.php') || $this->isPhpFile($executable)) {
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
            echo 'Full command: ' . implode(' ', $command) . "\n";
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
            // Check if we have STDIN input to pass through
            if ($this->hasStdinInput()) {
                // Handle STDIO passthrough for interactive processes (like MCP servers)
                return $this->runWithStdioPassthrough($process);
            } else {
                // Run the process normally and output directly
                $process->mustRun(function ($type, $buffer) {
                    echo $buffer;
                });

                return $process->getExitCode();
            }
        } catch (ProcessFailedException $e) {
            if ($this->debug) {
                echo 'Process failed: ' . $e->getMessage() . "\n";
            }

            return $e->getProcess()->getExitCode();
        }
    }

    private function hasStdinInput(): bool
    {
        // Check if STDIN has data available (non-blocking check)
        $read = [STDIN];
        $write = null;
        $except = null;
        
        // Use stream_select with 0 timeout to check if data is available
        $result = stream_select($read, $write, $except, 0);
        
        return $result > 0;
    }

    private function runWithStdioPassthrough(Process $process): int
    {
        if ($this->debug) {
            echo "Running with STDIO passthrough for interactive process\n";
        }

        // Read all input from STDIN first
        $input = '';
        while (!feof(STDIN)) {
            $line = fgets(STDIN);
            if ($line !== false) {
                $input .= $line;
                if ($this->debug) {
                    echo "Read from STDIN: " . trim($line) . "\n";
                }
            }
        }

        // Set the input for the process
        if (!empty($input)) {
            $process->setInput($input);
            if ($this->debug) {
                echo "Set process input: " . trim($input) . "\n";
            }
        }

        // Run the process and capture output
        $process->run(function ($type, $buffer) {
            // Forward all output directly to STDOUT/STDERR
            if ($type === Process::OUT) {
                echo $buffer;
            } else {
                fwrite(STDERR, $buffer);
            }
        });

        return $process->getExitCode();
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
