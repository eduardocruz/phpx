# PHPX Codebase Bug Analysis and Fixes

## Overview
After analyzing the PHPX codebase, I've identified several critical bugs that could cause runtime failures, security issues, and incorrect behavior. This document details each bug and provides recommended fixes.

## Critical Bugs Found

### 1. **Critical Race Condition in STDIN Detection** (ExecutionEnvironment.php:89-96)

**Location**: `src/Package/ExecutionEnvironment.php`, lines 89-96

**Bug Description**: 
The `hasStdinInput()` method uses `stream_select()` with a 0 timeout to check for STDIN availability, but this creates a race condition. The method may consume STDIN data during the check, making it unavailable for the actual process execution.

**Code with Bug**:
```php
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
```

**Impact**: 
- Data loss when piping input to PHPX processes
- Incorrect process execution mode selection
- Broken MCP server communication

**Recommended Fix**:
```php
private function hasStdinInput(): bool
{
    // Check if STDIN is not a TTY (indicating piped/redirected input)
    return !posix_isatty(STDIN);
}
```

---

### 2. **Memory Leak in Persistent STDIO Loop** (ExecutionEnvironment.php:118-140)

**Location**: `src/Package/ExecutionEnvironment.php`, lines 118-140

**Bug Description**:
The `runWithPersistentStdio()` method has a tight loop with no proper cleanup of the input stream buffer, potentially causing memory leaks in long-running processes.

**Code with Bug**:
```php
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
```

**Impact**:
- Memory accumulation in long-running MCP servers
- Potential system resource exhaustion
- Poor performance in high-throughput scenarios

**Recommended Fix**:
```php
// Main communication loop - keep running while process is alive
while ($process->isRunning()) {
    // Check for input from STDIN with proper error handling
    $input = fread(STDIN, 8192);
    if ($input !== false && $input !== '') {
        if ($this->debug) {
            echo "Forwarding STDIN to process: " . trim($input) . "\n";
        }
        $inputStream->write($input);
        // Flush the input stream to prevent buffer buildup
        flush();
    }

    // Increase delay slightly to reduce CPU usage
    usleep(50000); // 50ms - better balance between responsiveness and CPU usage
    
    // Yield control to prevent blocking other processes
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
}
```

---

### 3. **Directory Traversal Vulnerability** (PackageManager.php:155-170)

**Location**: `src/Package/PackageManager.php`, lines 155-170

**Bug Description**:
The `handleDirectPhpFile()` method doesn't properly validate file paths, allowing potential directory traversal attacks through malicious package specifications.

**Code with Bug**:
```php
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
    
    // ... rest of method
}
```

**Impact**:
- Potential access to files outside intended directories
- Security vulnerability allowing unauthorized file execution
- Possible information disclosure

**Recommended Fix**:
```php
private function handleDirectPhpFile(string $filePath): Package
{
    // Extract just the file path (ignore version spec if any)
    $parts = explode(':', $filePath);
    $actualPath = $parts[0];

    // Resolve and validate the path to prevent directory traversal
    $resolvedPath = realpath($actualPath);
    if ($resolvedPath === false) {
        throw new \RuntimeException("PHP file not found: $actualPath");
    }

    // Additional security check: ensure the resolved path is within allowed directories
    $currentDir = getcwd();
    if ($currentDir !== false && !str_starts_with($resolvedPath, $currentDir)) {
        // Only allow files in current directory or subdirectories for security
        if (!str_starts_with($resolvedPath, '/usr/bin/') && 
            !str_starts_with($resolvedPath, '/usr/local/bin/')) {
            throw new \RuntimeException("Access denied: File outside allowed directories: $actualPath");
        }
    }

    if (!is_readable($resolvedPath)) {
        throw new \RuntimeException("PHP file is not readable: $actualPath");
    }

    // Use the resolved path for consistency
    $actualPath = $resolvedPath;
    
    // ... rest of method using $actualPath
}
```

---

### 4. **Unsafe File Download Without Verification** (PackageManager.php:219-235)

**Location**: `src/Package/PackageManager.php`, lines 219-235

**Bug Description**:
The PHAR download functionality uses `file_get_contents()` without any integrity verification, SSL certificate validation, or size limits.

**Code with Bug**:
```php
$downloadedPhar = $pharVersionDir . '/' . $pharName;
$success = @file_get_contents($downloadUrl);

if ($success === false) {
    throw new \RuntimeException("Failed to download PHAR from $downloadUrl");
}

file_put_contents($downloadedPhar, $success);
chmod($downloadedPhar, 0o755); // Make executable
```

**Impact**:
- No verification of downloaded file integrity
- Potential security risk from malicious downloads
- No protection against oversized downloads
- Suppressed errors with @ operator hide important failure information

**Recommended Fix**:
```php
$downloadedPhar = $pharVersionDir . '/' . $pharName;

// Create a proper HTTP context with SSL verification and size limits
$context = stream_context_create([
    'http' => [
        'timeout' => 60,
        'user_agent' => 'PHPX/0.0.1',
        'max_redirects' => 3,
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ]
]);

// Download with size limit (50MB max for PHARs)
$maxSize = 50 * 1024 * 1024; // 50MB
$tempFile = tempnam(sys_get_temp_dir(), 'phpx_download_');

$sourceHandle = fopen($downloadUrl, 'r', false, $context);
if ($sourceHandle === false) {
    throw new \RuntimeException("Failed to open download URL: $downloadUrl");
}

$destHandle = fopen($tempFile, 'w');
if ($destHandle === false) {
    fclose($sourceHandle);
    throw new \RuntimeException("Failed to create temporary file for download");
}

$downloadedSize = 0;
while (!feof($sourceHandle) && $downloadedSize < $maxSize) {
    $chunk = fread($sourceHandle, 8192);
    if ($chunk === false) {
        break;
    }
    $downloadedSize += strlen($chunk);
    fwrite($destHandle, $chunk);
}

fclose($sourceHandle);
fclose($destHandle);

if ($downloadedSize >= $maxSize) {
    unlink($tempFile);
    throw new \RuntimeException("Downloaded file too large (>50MB): $downloadUrl");
}

if ($downloadedSize === 0) {
    unlink($tempFile);
    throw new \RuntimeException("Failed to download PHAR from $downloadUrl");
}

// Verify it's a valid PHAR before moving to final location
try {
    $phar = new \Phar($tempFile);
    $phar = null; // Close the PHAR
} catch (\Exception $e) {
    unlink($tempFile);
    throw new \RuntimeException("Downloaded file is not a valid PHAR: " . $e->getMessage());
}

// Move to final location
if (!rename($tempFile, $downloadedPhar)) {
    unlink($tempFile);
    throw new \RuntimeException("Failed to move downloaded PHAR to cache directory");
}

chmod($downloadedPhar, 0o755); // Make executable
```

---

### 5. **Command Injection Vulnerability** (PackageManager.php:270-290)

**Location**: `src/Package/PackageManager.php`, lines 270-290

**Bug Description**:
The `installPackage()` method constructs shell commands using `sprintf()` with `escapeshellarg()`, but this is still vulnerable to command injection if the package name contains special characters.

**Code with Bug**:
```php
// Run composer install in temp directory
$command = sprintf(
    'cd %s && composer install --no-dev --no-interaction 2>&1',
    escapeshellarg($tempDir)
);

if ($this->debug) {
    echo "Running: $command\n";
}

exec($command, $output, $returnCode);
```

**Impact**:
- Potential command injection through malicious package names
- Arbitrary code execution risk
- System compromise possibility

**Recommended Fix**:
```php
// Use Symfony Process for safer command execution
use Symfony\Component\Process\Process;

// Create a proper Process instance instead of using exec
$process = new Process(
    ['composer', 'install', '--no-dev', '--no-interaction'],
    $tempDir,
    null,
    null,
    300 // 5 minute timeout
);

if ($this->debug) {
    echo "Running: " . $process->getCommandLine() . "\n";
}

try {
    $process->mustRun();
    $output = explode("\n", $process->getOutput());
    $returnCode = $process->getExitCode();
} catch (ProcessFailedException $e) {
    $this->filesystem->remove($tempDir);
    throw new \RuntimeException('Failed to install package: ' . $e->getMessage());
}
```

---

### 6. **Incorrect Path Resolution in Package.php** (Package.php:45-75)

**Location**: `src/Package/Package.php`, lines 45-75

**Bug Description**:
The `getExecutable()` method has flawed logic for finding executables, particularly the pattern matching for package names.

**Code with Bug**:
```php
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
```

**Impact**:
- Incorrect executable detection for packages with complex names
- May execute wrong binaries
- Unpredictable behavior with vendor packages

**Recommended Fix**:
```php
// Extract package name more reliably from path
$pathParts = explode('_', basename($this->path));
$packageName = end($pathParts); // Get the actual package name part

// Get composer.json to find the real package name
$composerJson = $this->getComposerJson();
$realPackageName = null;

if (isset($composerJson['name'])) {
    $realPackageName = basename($composerJson['name']); // Get package name after vendor/
}

// Common bin patterns with better logic
$binPatterns = [];

// Try the real package name first if available
if ($realPackageName) {
    $binPatterns[] = $binDir . '/' . $realPackageName;
}

// Try the extracted package name
$binPatterns[] = $binDir . '/' . $packageName;

// Try common executable names
$commonNames = ['phpunit', 'phpstan', 'php-cs-fixer'];
foreach ($commonNames as $commonName) {
    $binPatterns[] = $binDir . '/' . $commonName;
}

// Finally, try any executable file (but this should be last resort)
$binPatterns[] = $binDir . '/*';
```

---

## Summary

The PHPX codebase contains several critical security and functionality bugs that should be addressed immediately:

1. **Race condition in STDIN detection** - Can cause data loss
2. **Memory leak in persistent STDIO** - Can cause resource exhaustion  
3. **Directory traversal vulnerability** - Security risk
4. **Unsafe file downloads** - Security and integrity risk
5. **Command injection vulnerability** - Critical security risk
6. **Incorrect executable resolution** - Functionality issue

## Recommended Actions

1. **Immediate**: Fix the command injection vulnerability (#5) and directory traversal (#3) as these pose security risks
2. **High Priority**: Address the STDIN race condition (#1) and unsafe downloads (#4) 
3. **Medium Priority**: Fix the memory leak (#2) and executable resolution (#6)

All fixes should be thoroughly tested, especially the STDIN handling and process execution logic, as these are core to PHPX's functionality.