# PHPX

PHPX is an NPX-like package execution tool for PHP. It allows you to execute PHP packages and PHAR files without installing them globally.

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

## Installation

### Option 1: Global Installation (Recommended)
```bash
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

PHPX can execute PHAR files in two ways:

1. From a local file:
```bash
phpx path/to/your-tool.phar [arguments]
```

2. Using known PHAR files:
```bash
# List available PHAR files
phpx list-phars

# Execute a known PHAR (will be downloaded and cached automatically)
phpx php-cs-fixer.phar fix src/
```

For known PHARs (like PHP CS Fixer, PHPUnit, etc.), PHPX will:
- First check if the PHAR exists in your current directory
- If not found locally, automatically download it from the official source
- Cache it in `~/.cache/phpx/phars/` for future use
- Execute it with your provided arguments

## Features

- Execute Composer packages without global installation
- Run PHAR files directly (both local and known PHARs)
- Automatic PHAR download and caching for known tools
- Automatic dependency resolution
- Package version selection
- Caching for better performance
- Clean execution environment for each run

## Cache

PHPX caches downloaded packages and PHARs in:
- Packages: `~/.cache/phpx/` (or `$XDG_CACHE_HOME/phpx/` if set)
- PHARs: `~/.cache/phpx/phars/`

You can safely delete these directories to clear the cache.

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