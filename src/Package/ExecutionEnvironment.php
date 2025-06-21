<?php

declare(strict_types=1);

namespace PHPX\Package;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\InputStream;

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
                // Handle persistent STDIO communication for interactive processes (like MCP servers)
                return $this->runWithPersistentStdio($process);
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

    private function runWithPersistentStdio(Process $process): int
    {
        if ($this->debug) {
            echo "Running with persistent STDIO for interactive process\n";
        }

        // Use InputStream for persistent input handling
        $inputStream = new \Symfony\Component\Process\InputStream();
        $process->setInput($inputStream);

        // Start the process asynchronously
        $process->start(function ($type, $buffer) {
            // Forward all output directly to appropriate streams
            if ($type === Process::OUT) {
                echo $buffer;
                flush();
            } else {
                fwrite(STDERR, $buffer);
                fflush(STDERR);
            }
        });

        // Set up non-blocking STDIN
        stream_set_blocking(STDIN, false);

        // Main communication loop - keep running while process is alive
        while ($process->isRunning()) {
            // Check for input from STDIN
            $input = fread(STDIN, 8192);
            if ($input !== false && $input !== '') {
                if ($this->debug) {
                    echo "Forwarding STDIN to process: " . trim($input) . "\n";
                }
                $inputStream->write($input);
            }

            // Small delay to prevent excessive CPU usage
            usleep(10000); // 10ms
        }

        // Close the input stream when done
        $inputStream->close();

        // Wait for the process to finish and get the exit code
        $exitCode = $process->wait();

        if ($this->debug) {
            echo "Process finished with exit code: $exitCode\n";
        }

        return $exitCode;
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
