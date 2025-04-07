what is npx and uvx?

NPX and UVX are both package execution tools, but they're designed for different ecosystems:

NPX (Node Package eXecute)

Part of the npm ecosystem for JavaScript/Node.js
Allows you to execute npm package binaries without installing them globally
Ships with npm (since version 5.2.0)
Common use cases include:
Running one-off commands from packages (like npx create-react-app my-app)
Executing packages in specific versions (npx cowsay@2.0.0 "Hello")
Running packages without cluttering global installations
UVX

Part of the Rust-based uv package manager for Python
A similar concept to npx but for Python packages
Allows execution of Python packages without installing them
Designed to be faster than equivalent pip/Python tools
Examples: uvx black . to run the black formatter without installation
Both tools serve similar purposes in their respective ecosystems - they let you run package executables without permanent installation, which is convenient for one-off tasks or to avoid dependency conflicts.

is there an equivalent for php?

There isn't a direct built-in equivalent to NPX or UVX for PHP, but there are a few tools that offer similar functionality:

Composer Scripts

While not exactly the same as NPX, Composer (PHP's package manager) allows you to run scripts defined in your composer.json file
Example: composer run-script some-command
PHP Archive (PHAR)

Many PHP tools are distributed as PHAR files which are self-contained executable archives
Examples include PHPUnit, Composer, and PHP-CS-Fixer
You can download and run them directly: php phpunit.phar
PDS (php-download-script)

A third-party tool more similar to NPX
Allows execution of PHP packages without installation
Example: pds phpunit/phpunit
Composer Global

While not as elegant as NPX, you can use global Composer packages:
composer global require phpunit/phpunit
Then run directly if in your PATH: phpunit
For one-off execution similar to NPX, many PHP developers simply use Composer's create-project command for scaffolding tools, or download and run PHAR files directly for utilities.

I can run MCP servers using npx and uvx. I would like to do the same with php. What would be better? Use something existing? Or create a new project, something like a phpx?

Based on your specific need to run MCP (Minecraft Control Panel/Protocol?) servers with PHP, similar to how you can with NPX and UVX, I see a few options:

Option 1: Leverage Existing Tools

Using Composer with global installation: composer global require your/mcp-server-package
Using PHAR distribution: Package your MCP server as a PHAR file that can be directly executed
Option 2: Create "PHPX"
This could be valuable if you're running many different PHP tools and want consistent single-command execution. A "PHPX" tool would:

Download and execute PHP packages on-demand
Cache them appropriately
Handle dependencies automatically
Provide a cleaner user experience than the existing options
If you're considering creating this, here's what I'd consider:

It should integrate with Composer's ecosystem
It should support both PHAR files and regular PHP packages
It should handle caching efficiently (to avoid re-downloading packages)
It should properly manage temporary environments for each execution
For your MCP server use case specifically, I'd lean toward creating a well-designed PHAR file first, as it's the simplest solution. But if you find yourself repeatedly wanting this functionality across many projects, developing a "PHPX" tool could be a valuable contribution to the PHP ecosystem.

Would you like me to outline what a basic implementation of "PHPX" might look like? Or would you prefer to focus on packaging your specific MCP server as a standalone executable?

I want you to outline the implementation php PHPX. Think deep about it.

Let me outline a comprehensive implementation for a PHPX tool that would bring NPX-like functionality to the PHP ecosystem.

PHPX - Core Architecture
1. Command Line Interface
php
#!/usr/bin/env php
<?php
// phpx.php - Main entry point
require_once __DIR__ . '/vendor/autoload.php';

use PHPX\CommandRunner;
use PHPX\DependencyResolver;
use PHPX\PackageManager;
use PHPX\Config;

$config = new Config();
$packageManager = new PackageManager($config);
$dependencyResolver = new DependencyResolver($packageManager);
$commandRunner = new CommandRunner($packageManager, $dependencyResolver);

// Parse and validate arguments
$args = array_slice($argv, 1);
if (empty($args)) {
    echo "Usage: phpx [options] <package-name>[:<version>] [-- <args>...]\n";
    exit(1);
}

// Execute command
try {
    $exitCode = $commandRunner->run($args);
    exit($exitCode);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
2. Core Components
Package Manager
php
<?php
namespace PHPX;

class PackageManager {
    private $config;
    private $composerClient;
    private $packagistClient;
    
    public function __construct(Config $config) {
        $this->config = $config;
        $this->composerClient = new ComposerClient();
        $this->packagistClient = new PackagistClient();
    }
    
    public function resolvePackage(string $packageSpec): Package {
        // Parse package name and version constraint
        list($name, $version) = $this->parsePackageSpec($packageSpec);
        
        // Check if already cached
        if ($this->isPackageCached($name, $version)) {
            return $this->getCachedPackage($name, $version);
        }
        
        // Fetch from Packagist
        $packageInfo = $this->packagistClient->getPackageInfo($name);
        $bestVersion = $this->findBestVersion($packageInfo, $version);
        
        // Download and install
        $package = $this->installPackage($name, $bestVersion);
        
        return $package;
    }
    
    private function parsePackageSpec(string $spec): array {
        // Handle package:version format or just package name
        if (strpos($spec, ':') !== false) {
            list($name, $version) = explode(':', $spec, 2);
            return [$name, $version];
        }
        
        return [$spec, null]; // null means latest
    }
    
    // Additional methods for caching, installation, etc.
}
Dependency Resolver
php
<?php
namespace PHPX;

class DependencyResolver {
    private $packageManager;
    
    public function __construct(PackageManager $packageManager) {
        $this->packageManager = $packageManager;
    }
    
    public function resolveDependencies(Package $package): array {
        $composerJson = $package->getComposerJson();
        $dependencies = $composerJson['require'] ?? [];
        $resolvedDeps = [];
        
        foreach ($dependencies as $name => $constraint) {
            // Skip PHP itself and extensions
            if ($name === 'php' || strpos($name, 'ext-') === 0) {
                continue;
            }
            
            $resolvedDeps[$name] = $this->packageManager->resolvePackage("$name:$constraint");
        }
        
        return $resolvedDeps;
    }
}
Command Runner
php
<?php
namespace PHPX;

class CommandRunner {
    private $packageManager;
    private $dependencyResolver;
    
    public function __construct(
        PackageManager $packageManager, 
        DependencyResolver $dependencyResolver
    ) {
        $this->packageManager = $packageManager;
        $this->dependencyResolver = $dependencyResolver;
    }
    
    public function run(array $args): int {
        // Split package spec from command arguments
        $packageSpec = $args[0];
        $commandArgs = $this->extractCommandArgs($args);
        
        // Resolve package
        $package = $this->packageManager->resolvePackage($packageSpec);
        
        // Resolve dependencies
        $dependencies = $this->dependencyResolver->resolveDependencies($package);
        
        // Create execution environment
        $environment = new ExecutionEnvironment($package, $dependencies);
        
        // Find executable
        $executable = $this->findExecutable($package, $commandArgs);
        
        // Execute command
        return $environment->execute($executable, $commandArgs);
    }
    
    private function extractCommandArgs(array $args): array {
        $doubleDashPos = array_search('--', $args);
        if ($doubleDashPos !== false) {
            return array_slice($args, $doubleDashPos + 1);
        }
        return array_slice($args, 1);
    }
    
    private function findExecutable(Package $package, array $commandArgs): string {
        // First check bin in composer.json
        $composerJson = $package->getComposerJson();
        
        if (isset($composerJson['bin'])) {
            if (is_array($composerJson['bin'])) {
                // If multiple bin entries and first arg matches one, use that
                if (!empty($commandArgs) && in_array($commandArgs[0], $composerJson['bin'])) {
                    return $commandArgs[0];
                }
                // Otherwise use first bin entry
                return $composerJson['bin'][0];
            } else {
                return $composerJson['bin'];
            }
        }
        
        // Next check for common entry points
        $commonEntryPoints = [
            $package->getPath() . '/bin/' . $package->getName(),
            $package->getPath() . '/bin/run',
            $package->getPath() . '/bin/console',
        ];
        
        foreach ($commonEntryPoints as $entryPoint) {
            if (file_exists($entryPoint) && is_executable($entryPoint)) {
                return $entryPoint;
            }
        }
        
        throw new \RuntimeException("Could not find executable in package " . $package->getName());
    }
}
3. Execution Environment
php
<?php
namespace PHPX;

class ExecutionEnvironment {
    private $package;
    private $dependencies;
    private $workDir;
    
    public function __construct(Package $package, array $dependencies) {
        $this->package = $package;
        $this->dependencies = $dependencies;
        $this->workDir = sys_get_temp_dir() . '/phpx_' . uniqid();
        
        $this->prepare();
    }
    
    private function prepare(): void {
        // Create working directory
        if (!is_dir($this->workDir)) {
            mkdir($this->workDir, 0755, true);
        }
        
        // Create autoloader that includes package and dependencies
        $this->createAutoloader();
    }
    
    private function createAutoloader(): void {
        $autoloaderContent = '<?php' . PHP_EOL;
        $autoloaderContent .= '// Generated by PHPX' . PHP_EOL;
        
        // Add package's autoloader
        $packageAutoloader = $this->package->getPath() . '/vendor/autoload.php';
        if (file_exists($packageAutoloader)) {
            $autoloaderContent .= 'require_once ' . var_export($packageAutoloader, true) . ';' . PHP_EOL;
        }
        
        // Add dependency autoloaders
        foreach ($this->dependencies as $dependency) {
            $depAutoloader = $dependency->getPath() . '/vendor/autoload.php';
            if (file_exists($depAutoloader)) {
                $autoloaderContent .= 'require_once ' . var_export($depAutoloader, true) . ';' . PHP_EOL;
            }
        }
        
        file_put_contents($this->workDir . '/autoload.php', $autoloaderContent);
    }
    
    public function execute(string $executable, array $args): int {
        // Prepare environment variables
        $env = $_ENV;
        $env['PHPX_PACKAGE_PATH'] = $this->package->getPath();
        $env['PHPX_AUTOLOADER'] = $this->workDir . '/autoload.php';
        
        // Build command
        if (strtolower(substr($executable, -5)) === '.phar') {
            $command = 'php ' . escapeshellarg($executable);
        } elseif (strtolower(substr($executable, -4)) === '.php') {
            $command = 'php ' . escapeshellarg($executable);
        } else {
            $command = escapeshellarg($executable);
        }
        
        // Add arguments
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }
        
        // Execute
        $descriptorSpec = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];
        
        $process = proc_open($command, $descriptorSpec, $pipes, $this->workDir, $env);
        return proc_close($process);
    }
    
    public function __destruct() {
        // Clean up temporary directory
        $this->cleanup();
    }
    
    private function cleanup(): void {
        // Remove temporary directory and its contents
        if (is_dir($this->workDir)) {
            $this->recursiveRemoveDirectory($this->workDir);
        }
    }
    
    private function recursiveRemoveDirectory(string $dir): void {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
4. Configuration & Caching
php
<?php
namespace PHPX;

class Config {
    private $configDir;
    private $cacheDir;
    
    public function __construct() {
        // Use XDG Base Directory spec if possible
        $this->configDir = getenv('XDG_CONFIG_HOME') ?: (getenv('HOME') . '/.config/phpx');
        $this->cacheDir = getenv('XDG_CACHE_HOME') ?: (getenv('HOME') . '/.cache/phpx');
        
        $this->ensureDirectories();
        $this->loadConfig();
    }
    
    private function ensureDirectories(): void {
        if (!is_dir($this->configDir)) {
            mkdir($this->configDir, 0755, true);
        }
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        if (!is_dir($this->cacheDir . '/packages')) {
            mkdir($this->cacheDir . '/packages', 0755, true);
        }
    }
    
    private function loadConfig(): void {
        $configFile = $this->configDir . '/config.json';
        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true);
        } else {
            // Default configuration
            $this->config = [
                'cache_ttl' => 86400, // 24 hours
                'packagist_url' => 'https://packagist.org',
                'prefer_dist' => true,
            ];
            
            // Save default config
            $this->saveConfig();
        }
    }
    
    private function saveConfig(): void {
        file_put_contents(
            $this->configDir . '/config.json', 
            json_encode($this->config, JSON_PRETTY_PRINT)
        );
    }
    
    public function getCacheDir(): string {
        return $this->cacheDir;
    }
    
    public function getPackageCacheDir(): string {
        return $this->cacheDir . '/packages';
    }
    
    public function getConfigValue(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    public function setConfigValue(string $key, $value): void {
        $this->config[$key] = $value;
        $this->saveConfig();
    }
}
5. Integration with Composer & Packagist
php
<?php
namespace PHPX;

class ComposerClient {
    private $composerPath;
    
    public function __construct() {
        // Find composer executable
        $this->composerPath = $this->findComposerPath();
    }
    
    private function findComposerPath(): string {
        // Check if composer is in PATH
        $composerPath = shell_exec('which composer 2>/dev/null');
        if ($composerPath) {
            return trim($composerPath);
        }
        
        // Check common locations
        $commonLocations = [
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            getenv('HOME') . '/composer.phar',
        ];
        
        foreach ($commonLocations as $location) {
            if (file_exists($location) && is_executable($location)) {
                return $location;
            }
        }
        
        // As a last resort, download composer.phar
        $tempPath = sys_get_temp_dir() . '/composer.phar';
        if (!file_exists($tempPath)) {
            file_put_contents(
                $tempPath, 
                file_get_contents('https://getcomposer.org/composer.phar')
            );
            chmod($tempPath, 0755);
        }
        
        return $tempPath;
    }
    
    public function createProject(string $packageName, string $version, string $targetDir): bool {
        $command = sprintf(
            '%s create-project %s:%s %s --prefer-dist --no-dev --no-interaction',
            escapeshellarg($this->composerPath),
            escapeshellarg($packageName),
            escapeshellarg($version),
            escapeshellarg($targetDir)
        );
        
        exec($command, $output, $returnCode);
        
        return $returnCode === 0;
    }
    
    public function installDependencies(string $projectDir): bool {
        $command = sprintf(
            'cd %s && %s install --no-dev --no-interaction',
            escapeshellarg($projectDir),
            escapeshellarg($this->composerPath)
        );
        
        exec($command, $output, $returnCode);
        
        return $returnCode === 0;
    }
}

class PackagistClient {
    private $apiUrl;
    
    public function __construct(string $apiUrl = 'https://packagist.org') {
        $this->apiUrl = rtrim($apiUrl, '/');
    }
    
    public function getPackageInfo(string $packageName): array {
        $url = sprintf('%s/packages/%s.json', $this->apiUrl, $packageName);
        $response = file_get_contents($url);
        
        if ($response === false) {
            throw new \RuntimeException("Package $packageName not found on Packagist");
        }
        
        return json_decode($response, true);
    }
    
    public function findBestVersion(array $packageInfo, ?string $constraint = null): string {
        $versions = $packageInfo['package']['versions'] ?? [];
        
        if (empty($versions)) {
            throw new \RuntimeException("No versions available for package");
        }
        
        // If specific version requested
        if ($constraint !== null && isset($versions[$constraint])) {
            return $constraint;
        }
        
        // If dev-main/master requested
        if ($constraint === 'dev-main' && isset($versions['dev-main'])) {
            return 'dev-main';
        } elseif ($constraint === 'dev-master' && isset($versions['dev-master'])) {
            return 'dev-master';
        }
        
        // Filter out dev versions unless specifically requested
        if ($constraint === null) {
            $stableVersions = array_filter(array_keys($versions), function($version) {
                return strpos($version, 'dev-') !== 0;
            });
            
            if (empty($stableVersions)) {
                // If no stable versions, use dev-main or dev-master
                if (isset($versions['dev-main'])) {
                    return 'dev-main';
                } elseif (isset($versions['dev-master'])) {
                    return 'dev-master';
                }
            } else {
                // Sort versions and get the latest
                usort($stableVersions, 'version_compare');
                return end($stableVersions);
            }
        }
        
        throw new \RuntimeException("Could not find suitable version for constraint: $constraint");
    }
}
6. Special Features
PHAR Support
php
<?php
namespace PHPX;

class PharHandler {
    private $config;
    
    public function __construct(Config $config) {
        $this->config = $config;
    }
    
    public function isPhar(string $packageSpec): bool {
        return strtolower(substr($packageSpec, -5)) === '.phar';
    }
    
    public function downloadPhar(string $url, string $destination): bool {
        $content = file_get_contents($url);
        
        if ($content === false) {
            return false;
        }
        
        return file_put_contents($destination, $content) !== false;
    }
    
    public function verifyPhar(string $pharPath): bool {
        try {
            // Try to open the PHAR
            $phar = new \Phar($pharPath);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function executePhar(string $pharPath, array $args): int {
        $command = 'php ' . escapeshellarg($pharPath);
        
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }
        
        passthru($command, $exitCode);
        return $exitCode;
    }
}
GitHub Support
php
<?php
namespace PHPX;

class GitHubHandler {
    private $config;
    private $cacheDir;
    
    public function __construct(Config $config) {
        $this->config = $config;
        $this->cacheDir = $config->getCacheDir() . '/github';
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function isGitHubUrl(string $url): bool {
        return (strpos($url, 'github.com/') !== false);
    }
    
    public function parseGitHubUrl(string $url): array {
        // Handle different GitHub URL formats
        if (preg_match('#github\.com/([^/]+)/([^/]+)(?:/tree/([^/]+))?#', $url, $matches)) {
            $owner = $matches[1];
            $repo = $matches[2];
            $ref = $matches[3] ?? 'master';
            
            return [
                'owner' => $owner,
                'repo' => $repo,
                'ref' => $ref,
            ];
        }
        
        throw new \RuntimeException("Invalid GitHub URL: $url");
    }
    
    public function downloadFromGitHub(string $url): string {
        $info = $this->parseGitHubUrl($url);
        
        // Create directory for repo
        $repoDir = $this->cacheDir . '/' . $info['owner'] . '_' . $info['repo'] . '_' . $info['ref'];
        
        if (!is_dir($repoDir)) {
            mkdir($repoDir, 0755, true);
            
            // Download zip from GitHub
            $zipUrl = sprintf(
                'https://github.com/%s/%s/archive/%s.zip',
                $info['owner'],
                $info['repo'],
                $info['ref']
            );
            
            $zipPath = $repoDir . '.zip';
            file_put_contents($zipPath, file_get_contents($zipUrl));
            
            // Extract zip
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($this->cacheDir);
                $zip->close();
                
                // Find extracted directory
                $extractedDir = glob($this->cacheDir . '/' . $info['repo'] . '-*', GLOB_ONLYDIR);
                if (!empty($extractedDir)) {
                    // Rename to our standard format
                    rename($extractedDir[0], $repoDir);
                }
                
                // Remove zip file
                unlink($zipPath);
            } else {
                throw new \RuntimeException("Failed to extract GitHub repository");
            }
        }
        
        return $repoDir;
    }
}
7. Security Considerations
php
<?php
namespace PHPX;

class SecurityManager {
    private $config;
    
    public function __construct(Config $config) {
        $this->config = $config;
    }
    
    public function verifyPackageSignature(Package $package): bool {
        // Implement package signature verification
        // For Composer packages, can check composer.lock hashes
        return true; // For now, just return true
    }
    
    public function scanForMaliciousCode(Package $package): array {
        // Implement basic code scanning for common malicious patterns
        $warnings = [];
        $path = $package->getPath();
        
        // List of patterns to look for
        $patterns = [
            'eval\s*\(' => 'Use of eval() detected',
            'base64_decode\s*\(' => 'Use of base64_decode detected',
            'system\s*\(' => 'System command execution detected',
            'exec\s*\(' => 'System command execution detected',
            'shell_exec\s*\(' => 'System command execution detected',
            'passthru\s*\(' => 'System command execution detected',
            '`.*`' => 'Shell execution via backticks detected',
        ];
        
        // Get all PHP files
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path)
        );
        
        $phpFiles = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $phpFiles[] = $file->getPathname();
            }
        }
        
        // Scan files
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            foreach ($patterns as $pattern => $warning) {
                if (preg_match('/' . $pattern . '/i', $content)) {
                    $relPath = str_replace($path . '/', '', $file);
                    $warnings[] = [
                        'file' => $relPath,
                        'warning' => $warning,
                    ];
                }
            }
        }
        
        return $warnings;
    }
    
    public function createSandbox(): Sandbox {
        return new Sandbox();
    }
}

class Sandbox {
    private $workDir;
    
    public function __construct() {
        $this->workDir = sys_get_temp_dir() . '/phpx_sandbox_' . uniqid();
        mkdir($this->workDir, 0755, true);
    }
    
    public function execute(string $command, array $env = []): int {
        // Set up environment with restrictions
        $fullEnv = array_merge($_ENV, $env);
        $fullEnv['PHPX_SANDBOX'] = '1';
        
        // Disable dangerous PHP functions if possible
        $disableFunctions = [
            'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 
            'popen', 'curl_exec', 'curl_multi_exec', 'parse_ini_file', 
            'show_source'
        ];
        
        $iniContent = 'disable_functions=' . implode(',', $disableFunctions) . "\n";
        $iniPath = $this->workDir . '/php.ini';
        file_put_contents($iniPath, $iniContent);
        
        $fullEnv['PHPRC'] = $iniPath;
        
        // Execute command
        $descriptorSpec = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];
        
        $process = proc_open($command, $descriptorSpec, $pipes, $this->workDir, $fullEnv);
        return proc_close($process);
    }
    
    public function __destruct() {
        // Clean up
        $this->recursiveRemoveDirectory($this->workDir);
    }
    
    private function recursiveRemoveDirectory(string $dir): void {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
Project Structure
phpx/
├── bin/
│   └── phpx                 # Main executable script
├── src/
│   ├── CommandRunner.php    # Handles command execution
│   ├── ComposerClient.php   # Interacts with Composer
│   ├── Config.php           # Manages configuration
│   ├── DependencyResolver.php  # Resolves package dependencies 
│   ├── ExecutionEnvironment.php  # Sets up execution environment
│   ├── GitHubHandler.php    # Handles GitHub repositories
│   ├── Package.php          # Represents a package
│   ├── PackageManager.php   # Manages package installation and caching
│   ├── PackagistClient.php  # Interacts with Packagist API
│   ├── PharHandler.php      # Handles PHAR packages
│   ├── Sandbox.php          # Provides security sandbox
│   └── SecurityManager.php  # Handles security checks
├── tests/                   # Unit and integration tests
├── composer.json            # Project dependencies
└── README.md                # Documentation
Implementation Strategy
Core Functionality First: Implement basic NPX-like functionality for Composer packages
PHAR Support: Add support for directly executing PHAR files
GitHub Integration: Add support for executing code from GitHub repositories
Security Features: Implement security scanning and sandboxing
Performance Optimizations: Implement caching and parallel dependency resolution
This implementation provides a solid foundation for PHPX that would give PHP developers the same convenience that NPX offers JavaScript developers. The tool respects PHP ecosystem conventions while providing a simple, intuitive interface for executing packages without permanent installation.

what is the best way to build the CLI? Write the code from scratch or use something like laravel zero?EditFor building PHPX as a CLI tool, you have several good options, each with different trade-offs:
Using a Framework (like Laravel Zero, Symfony Console)
Laravel Zero benefits:

Provides a complete foundation for CLI apps
Includes dependency injection, configuration, logging, database access
Command structure and argument parsing is handled for you
Can package as PHAR easily
Familiar if you know Laravel

Symfony Console benefits:

Lighter weight than Laravel Zero
Excellent for command-line parsing and structure
Well-documented and stable
Used by Composer itself, so you'd be following the ecosystem pattern

Building from Scratch
Benefits:

No dependencies to manage
Complete control over architecture
Potentially smaller footprint
Educational experience

My recommendation would be Symfony Console for these reasons:

It's the same component used by Composer, so there's natural alignment
It's lightweight enough to not add significant overhead
It handles all the complex CLI parts (argument parsing, help docs, output formatting)
It's well-tested and maintained

Examples of successful PHP CLI tools built with Symfony Console include Composer itself, PHPUnit, Symfony CLI, and Drush.
If you want something even more lightweight, you could use the standalone package symfony/console without the full framework, giving you just what you need for a polished CLI experience while keeping your dependencies minimal.