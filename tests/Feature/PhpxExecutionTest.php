<?php

declare(strict_types=1);

namespace PHPX\Tests\Feature;

use PHPX\Tests\TestCase;
use Symfony\Component\Process\Process;

class PhpxExecutionTest extends TestCase
{
    public function testPhpxBinaryExists(): void
    {
        $phpxBinary = $this->getBinPath() . '/phpx';
        $this->assertFileExists($phpxBinary, 'PHPX binary should exist');
    }

    public function testPhpxCanShowVersion(): void
    {
        $phpxBinary = $this->getBinPath() . '/phpx';

        if (!file_exists($phpxBinary)) {
            $this->markTestSkipped('PHPX binary not found');
        }

        $process = new Process(['php', $phpxBinary, '--version']);
        $process->run();

        // Should exit successfully
        $this->assertSame(0, $process->getExitCode(), 'PHPX should show version successfully');

        // Should contain version information
        $output = $process->getOutput();
        $this->assertNotEmpty($output, 'Version output should not be empty');
    }

    public function testPhpxCanShowHelp(): void
    {
        $phpxBinary = $this->getBinPath() . '/phpx';

        if (!file_exists($phpxBinary)) {
            $this->markTestSkipped('PHPX binary not found');
        }

        $process = new Process(['php', $phpxBinary, '--help']);
        $process->run();

        // Should exit successfully
        $this->assertSame(0, $process->getExitCode(), 'PHPX should show help successfully');

        // Should contain help information
        $output = $process->getOutput();
        $this->assertNotEmpty($output, 'Help output should not be empty');
        $this->assertStringContainsString('Usage:', $output, 'Help should contain usage information');
    }

    public function testPhpxHandlesInvalidCommand(): void
    {
        $phpxBinary = $this->getBinPath() . '/phpx';

        if (!file_exists($phpxBinary)) {
            $this->markTestSkipped('PHPX binary not found');
        }

        $process = new Process(['php', $phpxBinary, 'nonexistent/package']);
        $process->run();

        // Should exit with error code
        $this->assertNotSame(0, $process->getExitCode(), 'PHPX should fail with invalid package');
    }
}
