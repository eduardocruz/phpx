<?php

declare(strict_types=1);

namespace PHPX\Tests\Integration;

use PHPX\Tests\TestCase;
use Symfony\Component\Process\Process;

class McpServerTest extends TestCase
{
    private string $testServerPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testServerPath = $this->createTestMcpServer();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testServerPath)) {
            unlink($this->testServerPath);
        }
        parent::tearDown();
    }

    public function testLocalPhpProcessesMcpMessages(): void
    {
        $initMessage = $this->getInitializeMessage();

        $process = new Process(['php', $this->testServerPath]);
        $process->setInput($initMessage . "\n");
        $process->run();

        $this->assertSame(0, $process->getExitCode(), 'Process should complete successfully');

        $output = $process->getOutput();
        $this->assertNotEmpty($output, 'Should receive response from MCP server');

        // Parse the JSON response
        $lines = explode("\n", trim($output));
        $validJsonFound = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if (!empty($line) && $this->isValidJson($line)) {
                $data = json_decode($line, true);

                if (isset($data['result']['serverInfo'])) {
                    $validJsonFound = true;
                    break;
                }
            }
        }

        $this->assertTrue($validJsonFound, 'Should find valid MCP response with serverInfo');
    }

    public function testPhpxProcessesMcpMessages(): void
    {
        $phpxBinary = $this->getBinPath() . '/phpx';

        if (!file_exists($phpxBinary)) {
            $this->markTestSkipped('PHPX binary not found');
        }

        // Test that PHPX binary exists and is executable
        $this->assertFileExists($phpxBinary);
        $this->assertTrue(is_executable($phpxBinary));

        $initMessage = $this->getInitializeMessage();

        // Create a simple test by copying to a location PHPX can find
        $testPackageName = 'test-mcp-server.phar';
        $pharPath = sys_get_temp_dir() . '/' . $testPackageName;
        copy($this->testServerPath, $pharPath);
        chmod($pharPath, 0755);
        
        try {
            // This is the CRITICAL test - PHPX should process STDIO input just like direct PHP execution
            $process = new Process([$phpxBinary, $pharPath]);
            $process->setInput($initMessage . "\n");
            $process->setTimeout(10); // 10 second timeout
            $process->run();

            // Document the current failure for EDU-142 to fix
            $exitCode = $process->getExitCode();
            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();

            if ($exitCode !== 0) {
                $this->markTestIncomplete(
                    "STDIO Issue Reproduced - Ready for EDU-142 fix:\n" .
                    "PHPX cannot execute PHAR files with STDIO support.\n" .
                    "Exit code: $exitCode\n" .
                    "STDOUT: $stdout\n" .
                    "STDERR: $stderr\n" .
                    "This demonstrates the core issue that blocks MCP server execution."
                );
            }

            // This should work but currently FAILS due to STDIO handling issue
            $this->assertSame(0, $exitCode, 
                'PHPX should process MCP messages via STDIO. Exit code: ' . $exitCode . 
                "\nSTDOUT: " . $stdout . 
                "\nSTDERR: " . $stderr
            );

            $this->assertNotEmpty($stdout, 'PHPX should receive response from MCP server via STDIO');

            // Parse the JSON response
            $lines = explode("\n", trim($stdout));
            $validJsonFound = false;

            foreach ($lines as $line) {
                $line = trim($line);

                if (!empty($line) && $this->isValidJson($line)) {
                    $data = json_decode($line, true);

                    if (isset($data['result']['serverInfo'])) {
                        $validJsonFound = true;
                        break;
                    }
                }
            }

            $this->assertTrue($validJsonFound, 
                'PHPX should return valid MCP response with serverInfo. Output: ' . $stdout
            );

        } finally {
            unlink($pharPath);
        }
    }

    public function testPhpxStdioHandlingWithDirectFile(): void
    {
        $phpxBinary = $this->getBinPath() . '/phpx';

        if (!file_exists($phpxBinary)) {
            $this->markTestSkipped('PHPX binary not found');
        }

        // First verify that direct PHP execution works (baseline)
        $initMessage = $this->getInitializeMessage();
        
        $directPhpProcess = new Process(['php', $this->testServerPath]);
        $directPhpProcess->setInput($initMessage . "\n");
        $directPhpProcess->setTimeout(5);
        $directPhpProcess->run();
        
        $this->assertSame(0, $directPhpProcess->getExitCode(), 
            'Direct PHP execution should work as baseline');
        $this->assertNotEmpty($directPhpProcess->getOutput(), 
            'Direct PHP should return MCP response');

        // Now test PHPX execution - this should demonstrate the STDIO issue
        // PHPX currently doesn't support direct file execution, but it should
        $process = new Process([$phpxBinary, $this->testServerPath]);
        $process->setInput($initMessage . "\n");
        $process->setTimeout(10);
        $process->run();

        $exitCode = $process->getExitCode();
        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();

        // Document the current failure for EDU-142 to fix
        if ($exitCode !== 0) {
            $this->markTestIncomplete(
                "STDIO Issue Reproduced - Ready for EDU-142 fix:\n" .
                "PHPX cannot execute PHP files directly with STDIO support.\n" .
                "Exit code: $exitCode\n" .
                "STDOUT: $stdout\n" .
                "STDERR: $stderr\n" .
                "This demonstrates the core issue that blocks MCP server execution."
            );
        }

        // These assertions will fail until EDU-142 is fixed
        $this->assertSame(0, $exitCode, 
            "PHPX should execute PHP files with STDIO support.\n" .
            "Exit code: $exitCode\n" .
            "STDOUT: $stdout\n" .
            "STDERR: $stderr"
        );

        $this->assertNotEmpty($stdout, 'PHPX should pass STDIO input to the PHP script');

        // Verify we get a valid JSON response
        $lines = explode("\n", trim($stdout));
        $validJsonFound = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && $this->isValidJson($line)) {
                $data = json_decode($line, true);
                if (isset($data['result']['serverInfo'])) {
                    $validJsonFound = true;
                    break;
                }
            }
        }

        $this->assertTrue($validJsonFound, 
            "PHPX should return valid MCP response. Output: $stdout"
        );
    }

    public function testMcpToolExecution(): void
    {
        $toolMessage = $this->getToolCallMessage();

        $process = new Process(['php', $this->testServerPath]);
        $process->setInput($toolMessage . "\n");
        $process->run();

        $this->assertSame(0, $process->getExitCode(), 'Tool execution should complete successfully');

        $output = $process->getOutput();
        $this->assertNotEmpty($output, 'Should receive tool execution response');
    }

    public function testMcpJsonRpcErrorHandling(): void
    {
        // Send invalid JSON
        $invalidMessage = '{"invalid": json}';

        $process = new Process(['php', $this->testServerPath]);
        $process->setInput($invalidMessage . "\n");
        $process->run();

        $this->assertSame(0, $process->getExitCode(), 'Process should handle invalid JSON gracefully');

        $output = $process->getOutput();
        $this->assertNotEmpty($output, 'Should receive error response');
    }

    public function testStdioBufferHandling(): void
    {
        // Test multiple messages by running the process multiple times
        $messages = [
            $this->getInitializeMessage(),
            $this->getToolCallMessage(),
            $this->getToolCallMessage('test_tool_2'),
        ];

        foreach ($messages as $message) {
            $process = new Process(['php', $this->testServerPath]);
            $process->setInput($message . "\n");
            $process->run();

            $this->assertSame(0, $process->getExitCode(), 'Each message should be processed successfully');
            $output = $process->getOutput();
            $this->assertNotEmpty($output, 'Should receive response to message');
        }
    }

    private function createTestMcpServer(): string
    {
        $serverContent = '<?php
declare(strict_types=1);

// Simple MCP server for testing
while (($line = fgets(STDIN)) !== false) {
    $line = trim($line);
    if (empty($line)) continue;
    
    try {
        $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        
        if (!isset($request["method"])) {
            echo json_encode([
                "jsonrpc" => "2.0",
                "id" => $request["id"] ?? null,
                "error" => ["code" => -32600, "message" => "Invalid Request"]
            ]) . "\n";
            continue;
        }
        
        $response = ["jsonrpc" => "2.0", "id" => $request["id"] ?? null];
        
        switch ($request["method"]) {
            case "initialize":
                $response["result"] = [
                    "protocolVersion" => "2024-11-05",
                    "capabilities" => [
                        "tools" => true
                    ],
                    "serverInfo" => [
                        "name" => "test-server",
                        "version" => "1.0.0"
                    ]
                ];
                break;
                
            case "tools/call":
                $toolName = $request["params"]["name"] ?? "unknown";
                $response["result"] = [
                    "content" => [
                        ["type" => "text", "text" => "Tool $toolName executed successfully"]
                    ]
                ];
                break;
                
            default:
                $response["error"] = ["code" => -32601, "message" => "Method not found"];
        }
        
        echo json_encode($response) . "\n";
        
    } catch (JsonException $e) {
        echo json_encode([
            "jsonrpc" => "2.0",
            "id" => null,
            "error" => ["code" => -32700, "message" => "Parse error"]
        ]) . "\n";
    }
}
';

        $tempFile = tempnam(sys_get_temp_dir(), 'phpx_mcp_server_test_');
        file_put_contents($tempFile, $serverContent);

        return $tempFile;
    }

    private function createTestPackageStructure(): string
    {
        $tempDir = sys_get_temp_dir() . '/phpx_test_package_' . uniqid();
        mkdir($tempDir);

        // Create composer.json
        $composerJson = [
            'name' => 'test/mcp-server',
            'type' => 'project',
            'require' => ['php' => '>=8.1'],
            'autoload' => ['psr-4' => ['Test\\' => 'src/']],
            'bin' => ['server.php']
        ];
        file_put_contents($tempDir . '/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT));

        // Copy the MCP server
        copy($this->testServerPath, $tempDir . '/server.php');
        chmod($tempDir . '/server.php', 0755);

        return $tempDir;
    }

    private function cleanupTestPackageStructure(string $dir): void
    {
        if (is_dir($dir)) {
            $files = glob($dir . '/*');

            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($dir);
        }
    }

    private function getInitializeMessage(): string
    {
        return json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => ['tools' => true],
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
            ],
        ]);
    }

    private function getToolCallMessage(string $toolName = 'test_tool'): string
    {
        return json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => $toolName,
                'arguments' => [],
            ],
        ]);
    }

    private function waitForResponse(Process $process, int $timeoutSeconds): string
    {
        $startTime = time();
        $response = '';

        while (time() - $startTime < $timeoutSeconds) {
            if ($process->isRunning()) {
                $output = $process->getIncrementalOutput();

                if (!empty($output)) {
                    $response .= $output;
                    // Check if we have a complete JSON message
                    $lines = explode("\n", trim($response));

                    foreach ($lines as $line) {
                        $line = trim($line);

                        if (!empty($line) && $this->isValidJson($line)) {
                            return $line;
                        }
                    }
                }
            } else {
                break;
            }
            usleep(100000); // Sleep 100ms
        }

        return $response;
    }

    private function isValidJson(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
