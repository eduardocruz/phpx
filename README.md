# PHPX

PHPX is an NPX-like package execution tool for PHP. It allows you to execute PHP packages and PHAR files without installing them globally, featuring a beautiful and user-friendly command-line interface powered by Laravel Prompts.

## Requirements

- PHP 8.1 or higher
- Composer 2.0 or higher
- Terminal with support for ANSI escape sequences (for interactive features)

## Initial Motivation

PHPX was inspired by similar tools in other ecosystems:
- `npx` for Node.js (https://github.com/npm/npx) - Executes Node.js packages
- `uvx` for Python (https://github.com/astral-sh/uv) - Executes Python packages

The Model Context Protocol (MCP) is an open protocol that enables seamless integration between LLM applications and external data sources and tools. Modern AI-powered code editors like Cursor, Windsurf, and Claude Desktop use MCP Servers to provide AI capabilities in development environments.

Having package execution tools like `npx` and `uvx` allows developers to easily run MCP Servers without permanent installation. However, PHP lacked such a tool, creating a gap for PHP developers working with AI-assisted coding tools.

PHPX was created to bridge this gap, enabling PHP projects to be easily integrated with the AI-assisted development ecosystem, particularly when working with MCP-compatible editors and tools (https://github.com/modelcontextprotocol/servers).

## Why PHPX?

Even if you already have Composer and your common PHP tools installed, PHPX offers several advantages:

### Use Cases and Benefits

1. **Try Before You Install**
   - Test new tools without adding them to your project
   - `phpx phpstan/phpstan analyse src/` without modifying composer.json

2. **Version Flexibility**
   - Use different versions of tools without changing your project requirements
   - `phpx phpunit/phpunit:9.6 --test-suffix=...` for a one-off run with a specific version

3. **Standardized CI Environments**
   - Ensure everyone uses the same version of tools regardless of local installations
   - `phpx friendsofphp/php-cs-fixer:3.15 fix src/` in CI scripts

4. **Project Isolation**
   - Keep analysis tools separate from your project's runtime dependencies
   - Avoid dependency conflicts between your code and tool requirements

5. **Training and Onboarding**
   - Let new team members use standard tools without complex setup
   - Share commands that work regardless of local environment

6. **One-off Command Execution**
   - Run infrequently used tools without permanent installation
   - `phpx ramsey/uuid-console gen` to generate a UUID once

PHPX stands out from alternatives by using Composer's dependency resolution, providing access to any package on Packagist, and handling both Composer packages and PHAR files seamlessly.

## Features

- Execute Composer packages without global installation
- Run PHAR files directly (both local and known PHARs)
- Beautiful interactive command-line interface powered by Laravel Prompts
- Smart package search and selection
- Progress indicators for long-running operations
- User-friendly error messages and confirmations
- Automatic PHAR download and caching for known tools
- Automatic dependency resolution
- Package version selection
- Caching for better performance
- Clean execution environment for each run

## Installation

### Option 1: Global Installation (Recommended)
```bash
# Ensure you have PHP 8.1 or higher installed
php -v

# Install PHPX globally
composer global require eduardocruz/phpx
```
This will automatically make the `phpx` command available in your system if your global Composer bin directory is in your PATH.

### Option 2: Manual Installation
1. Clone the repository:
```bash
git clone https://github.com/eduardocruz/phpx.git
cd phpx
```

2. Install dependencies:
```bash
composer install
```

3. Set up the executable:
```bash
# Make the script executable
chmod +x bin/phpx

# Add to PATH (choose one):
# Temporary: Add to current session
export PATH="$PATH:$(pwd)/bin"

# Permanent: Add to your shell configuration (~/.bashrc, ~/.zshrc, etc.)
echo 'export PATH="$PATH:/path/to/phpx/bin"' >> ~/.zshrc
```

## Usage

### Execute a Composer package:
```bash
phpx vendor/package[:version] [arguments]
```

Example:
```bash
# Run PHPUnit without installing it globally
phpx phpunit/phpunit:^9.0 --version
```

### Execute a PHAR file:

PHPX can execute PHAR files in three ways:

1. From a local file:
```bash
phpx path/to/your-tool.phar [arguments]
```

2. Using known PHAR files (full name):
```bash
# List available PHAR files and their aliases
phpx list-phars

# Execute a known PHAR (latest version)
phpx php-cs-fixer.phar fix src/

# Execute a specific version
phpx php-cs-fixer.phar:3.26 fix src/
```

3. Using aliases (shorter form):
```bash
# Using alias (latest version)
phpx cs-fixer fix src/

# Using alias with version
phpx cs-fixer:3.26 fix src/
phpx phpunit:9 --filter MyTest
```

Built-in aliases include:
- `cs-fixer` → `php-cs-fixer.phar`
- `phpunit` → `phpunit.phar`
- `phpstan` → `phpstan.phar`
- `composer` → `composer.phar`

For known PHARs (like PHP CS Fixer, PHPUnit, etc.), PHPX will:
- First check if the PHAR exists in your current directory
- If not found locally, automatically download it from the official source
- Cache it in `~/.cache/phpx/phars/` for future use
- Execute it with your provided arguments

### Version Specification

PHPX supports version specification for both Composer packages and PHAR files:

1. Composer packages:
```bash
phpx phpunit/phpunit:^9.0 --version    # Semver constraint
phpx phpstan/phpstan:1.10.* analyse    # Version pattern
```

2. PHAR files:
```bash
phpx php-cs-fixer.phar:3.26 fix        # Specific version
phpx phpunit.phar:9 --filter Test      # Major version
phpx composer.phar:2 install           # Major version
phpx php-cs-fixer.phar:latest fix      # Latest version (default)
```

Available versions for each PHAR can be viewed using:
```bash
phpx list-phars
```

Each version is cached separately, allowing you to have multiple versions of the same tool available locally.

## Interactive Features

PHPX provides an enhanced user experience with interactive features powered by Laravel Prompts:

1. **Smart Package Selection**
   - Search and filter packages interactively
   - View package details and versions before installation
   - Auto-completion for package names

2. **Progress Indicators**
   - Visual progress bars for package downloads
   - Spinners for long-running operations
   - Clear status updates during execution

3. **User-Friendly Prompts**
   - Interactive version selection
   - Confirmation dialogs for important actions
   - Beautiful error messages with helpful suggestions

4. **Cache Management**
   - Interactive cache browsing and cleanup
   - Visual size indicators
   - Selective cache clearing

### Non-Interactive Mode

For CI/CD environments or scripting, all interactive features can be bypassed using command-line arguments or the `--no-interaction` flag:

```bash
# Non-interactive execution
phpx --no-interaction phpunit/phpunit:^9.0 --version
```

## Cache

PHPX caches downloaded packages and PHARs in:
- Packages: `~/.cache/phpx/` (or `$XDG_CACHE_HOME/phpx/` if set)
- PHARs: `~/.cache/phpx/phars/`

### Cache Management

PHPX provides commands to manage the cache:

```bash
# View cache size with detailed breakdown
phpx cache:size

# Clear the cache
phpx cache:clear
```

The `cache:size` command displays a table with size information for each package in the cache, sorted from largest to smallest, with a total at the bottom.

The `cache:clear` command removes all cached packages and PHARs after confirmation, freeing up disk space.

You can also manually delete these directories to clear the cache.

## Security

PHPX is designed with security in mind:
- Secure autoloader resolution
- No arbitrary directory traversal
- Proper permission handling
- Safe package execution environment
- Downloads PHARs only from official sources

## Troubleshooting

### Autoloader Not Found
If you get an autoloader error:
```bash
# Ensure dependencies are installed
composer install

# If using global installation, ensure Composer's global autoloader is available
composer global update
```

### Permission Issues
If you get a "permission denied" error:
```bash
# Check script permissions
ls -l bin/phpx
# Should show: -rwxr-xr-x or similar

# Fix permissions if needed
chmod +x bin/phpx
```

## Development

The project follows PHP best practices:
- PSR-12 coding standard
- Secure by default
- Proper dependency management
- Clear error handling

## License

MIT

## 🚀 Support phpx

If you found **phpx** helpful, believe in its potential, or simply want to support meaningful open-source contributions, please consider becoming a sponsor. Your support helps sustain continuous improvements, new features, and ongoing maintenance.

Whether you're actively using **phpx**, exploring its possibilities, or just excited by its mission—your contribution makes a significant difference.

👉 [Become a Sponsor](https://github.com/sponsors/eduardocruz)

Thank you for empowering open source!

## CI/CD Pipeline

PHPX uses a comprehensive CI/CD pipeline to ensure code quality and reliability:

### Automated Testing

- **Multi-Platform Testing**: Tests run on Ubuntu and macOS
- **Multi-PHP Version**: Supports PHP 8.1, 8.2, and 8.3
- **Test Coverage**: Unit, integration, and feature tests with coverage reporting
- **Automated Test Execution**: Tests run on every push and pull request

### Code Quality Checks


- **PHP CS Fixer**: Automated code style fixing with PSR-12 compliance
- **PHPMD**: Mess detection for code quality issues
- **PHP_CodeSniffer**: Additional code style validation

### Security Analysis

- **CodeQL**: Automated security vulnerability scanning
- **Composer Audit**: Dependency security vulnerability checks


### Automated Workflows

1. **Continuous Integration** (`.github/workflows/ci.yml`)
   - Runs on every push and pull request
   - Multi-matrix testing across OS and PHP versions
   - Code quality checks and security scans
   - Build artifact generation

2. **Release Automation** (`.github/workflows/release.yml`)
   - Triggered on version tags (`v*`)
   - Automated PHAR building and release creation
   - GitHub release with downloadable artifacts

3. **Security Scanning** (`.github/workflows/codeql.yml`)
   - Weekly scheduled security analysis
   - Real-time vulnerability detection
   - Automated security reporting



### Development Workflow

```bash
# Install dependencies
composer install

# Run tests
composer test

# Check code style
composer cs-check

# Fix code style
composer cs-fix

# Run all quality checks
composer ci
```

### Quality Standards

- **Test Coverage**: Comprehensive test suite covering core functionality
- **Code Style**: PSR-12 compliance with additional formatting rules

- **Security**: Regular vulnerability scanning and dependency updates
- **Documentation**: Inline documentation and comprehensive README

## Development

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suites
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit tests/Integration
vendor/bin/phpunit tests/Feature
```

### Code Quality

```bash
# Check code style
composer cs-check

# Fix code style automatically
composer cs-fix



# Run mess detection
composer phpmd
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run the quality checks
5. Submit a pull request

All contributions must pass the CI/CD pipeline before merging.
