<?php

declare(strict_types=1);

namespace PHPX\Tests\Integration;

use PHPX\Tests\TestCase;
use Symfony\Component\Process\Process;

class StdioHandlingTest extends TestCase
{
    public function testPhpScriptCanReadFromStdin(): void
    {
        $testScript = $this->createTempPhpScript('
            <?php
            $input = fgets(STDIN);
            echo "Received: " . trim($input);
        ');

        $process = new Process(['php', $testScript]);
        $process->setInput('test message');
        $process->run();

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('Received: test message', $process->getOutput());

        unlink($testScript);
    }

    public function testPhpScriptCanProcessJsonInput(): void
    {
        $testScript = $this->createTempPhpScript('
            <?php
            $input = fgets(STDIN);
            $data = json_decode(trim($input), true);
            if ($data && isset($data["method"])) {
                echo json_encode(["result" => "processed " . $data["method"]]);
            } else {
                echo json_encode(["error" => "invalid input"]);
            }
        ');

        $jsonInput = json_encode(['method' => 'test_method', 'params' => []]);

        $process = new Process(['php', $testScript]);
        $process->setInput($jsonInput);
        $process->run();

        $this->assertSame(0, $process->getExitCode());

        $output = $process->getOutput();
        $result = json_decode($output, true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertSame('processed test_method', $result['result']);

        unlink($testScript);
    }

    public function testPhpScriptCanHandleMultipleInputLines(): void
    {
        $testScript = $this->createTempPhpScript('
            <?php
            $count = 0;
            while (($line = fgets(STDIN)) !== false) {
                $count++;
                echo "Line $count: " . trim($line) . "\n";
                if ($count >= 3) break; // Prevent infinite loop
            }
        ');

        $input = "first line\nsecond line\nthird line\n";

        $process = new Process(['php', $testScript]);
        $process->setInput($input);
        $process->run();

        $this->assertSame(0, $process->getExitCode());

        $output = $process->getOutput();
        $this->assertStringContainsString('Line 1: first line', $output);
        $this->assertStringContainsString('Line 2: second line', $output);
        $this->assertStringContainsString('Line 3: third line', $output);

        unlink($testScript);
    }

    /**
     * Create a temporary PHP script for testing.
     */
    private function createTempPhpScript(string $content): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpx_test_');
        file_put_contents($tempFile, $content);

        return $tempFile;
    }
}
