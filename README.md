# PHPX

PHPX is an NPX-like package execution tool for PHP. It allows you to execute PHP packages and PHAR files without installing them globally.

## Installation

### Option 1: Global Installation (Recommended)
```bash
composer global require phpx/phpx
```
This will automatically make the `phpx` command available in your system if your global Composer bin directory is in your PATH.

### Option 2: Manual Installation
1. Clone the repository:
```bash
git clone https://github.com/yourusername/phpx.git
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

Execute a Composer package:
```bash
phpx vendor/package[:version] [arguments]
```

Execute a PHAR file:
```bash
phpx path/to/file.phar [arguments]
```

Examples:
```bash
# Run PHPUnit without installing it globally
phpx phpunit/phpunit:^9.0 --version

# Run PHP-CS-Fixer
phpx friendsofphp/php-cs-fixer fix src/

# Run a PHAR file
phpx php-cs-fixer.phar fix src/
```

## Features

- Execute Composer packages without global installation
- Run PHAR files directly
- Automatic dependency resolution
- Package version selection
- Caching for better performance
- Clean execution environment for each run

## Cache

PHPX caches downloaded packages in `~/.cache/phpx/` (or `$XDG_CACHE_HOME/phpx/` if set). You can safely delete this directory to clear the cache.

## Security

PHPX is designed with security in mind:
- Secure autoloader resolution
- No arbitrary directory traversal
- Proper permission handling
- Safe package execution environment

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