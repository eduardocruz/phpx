# PHPX

PHPX is an NPX-like package execution tool for PHP. It allows you to execute PHP packages and PHAR files without installing them globally.

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/phpx.git
cd phpx
```

2. Install dependencies:
```bash
composer install
```

3. Make the binary executable:
```bash
chmod +x bin/phpx
```

4. (Optional) Create a symlink to make PHPX available globally:
```bash
sudo ln -s $(pwd)/bin/phpx /usr/local/bin/phpx
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

## License

MIT