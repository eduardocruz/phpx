# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build/Lint/Test Commands
- Install dependencies: `composer install`
- Run tests: `vendor/bin/phpunit`
- Run single test: `vendor/bin/phpunit --filter TestName`
- Check code style: `vendor/bin/php-cs-fixer fix --dry-run`
- Fix code style: `vendor/bin/php-cs-fixer fix`
- Static analysis: `vendor/bin/phpstan analyse src`

## Code Style Guidelines
- PSR-12 coding standard
- Use strict typing (`declare(strict_types=1);`) in all PHP files
- Classes: PascalCase (e.g., `PackageManager`)
- Methods/functions: camelCase (e.g., `resolvePackage()`)
- Variables: camelCase (e.g., `$packageSpec`)
- Constants: UPPERCASE_WITH_UNDERSCORES
- Namespaces: follow PSR-4 autoloading standard
- Error handling: use exceptions with descriptive messages
- Documentation: PHPDoc blocks for classes and methods
- Types: use type hints and return types for all methods
- Imports: one use statement per line, alphabetically ordered