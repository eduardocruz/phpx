<?php

declare(strict_types=1);

namespace PHPX\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use PHPX\Package\PackageManager;

class ListPharsCommand extends Command
{
    protected static $defaultName = 'list-phars';
    protected static $defaultDescription = 'List all known PHAR files that can be executed directly';

    protected function configure(): void
    {
        $this->setHelp('This command shows all PHAR files that PHPX can download and execute automatically.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packageManager = new PackageManager();
        $knownPhars = $packageManager->getKnownPhars();
        $pharAliases = array_flip($packageManager->getPharAliases());

        // Prepare table data with file sizes
        $tableData = [];
        foreach ($knownPhars as $name => $versions) {
            $versionKeys = array_keys($versions);
            $latestVersion = array_key_exists('latest', $versions) ? 'latest' : end($versionKeys);
            $pharPath = $this->getPharPath($name, $latestVersion);
            $size = file_exists($pharPath) ? $this->formatSize(filesize($pharPath)) : 'Not downloaded';
            
            $tableData[] = [
                'name' => $name,
                'versions' => implode(', ', array_keys($versions)),
                'aliases' => isset($pharAliases[$name]) ? $pharAliases[$name] : '',
                'size' => $size,
            ];
        }

        // Sort options
        $sortOptions = [
            'name' => 'Sort by name',
            'size' => 'Sort by size',
            'versions' => 'Sort by number of versions',
        ];

        // Get user's sort preference
        $sortBy = select(
            label: 'Sort PHARs by:',
            options: $sortOptions,
            default: 'name'
        );

        // Apply sorting
        usort($tableData, function ($a, $b) use ($sortBy) {
            return match($sortBy) {
                'name' => strcmp($a['name'], $b['name']),
                'size' => strcmp($a['size'], $b['size']),
                'versions' => substr_count($b['versions'], ',') - substr_count($a['versions'], ','),
                default => 0
            };
        });

        // Filter option
        $filter = text(
            label: 'Filter PHARs (leave empty to show all):',
            placeholder: 'Enter name or alias to filter',
            required: false
        );

        if ($filter) {
            $filter = strtolower($filter);
            $tableData = array_filter($tableData, function ($row) use ($filter) {
                return str_contains(strtolower($row['name']), $filter) ||
                       str_contains(strtolower($row['aliases']), $filter) ||
                       str_contains(strtolower($row['versions']), $filter);
            });
        }

        // Display table with Laravel Prompts
        if (empty($tableData)) {
            $output->writeln('<info>No PHARs found matching your criteria.</info>');
            return Command::SUCCESS;
        }

        table(
            ['PHAR Name', 'Available Versions', 'Aliases', 'Size'],
            array_map(fn($row) => [
                $row['name'],
                $row['versions'],
                $row['aliases'],
                $row['size']
            ], $tableData)
        );

        $output->writeln('');
        $output->writeln('Usage examples:');
        $output->writeln('  phpx <phar-name>[:<version>] [arguments]');
        $output->writeln('  phpx <alias>[:<version>] [arguments]');
        $output->writeln('');
        $output->writeln('For instance:');
        $output->writeln('  phpx php-cs-fixer.phar fix src/                  # Full name');
        $output->writeln('  phpx cs-fixer fix src/                          # Using alias');
        $output->writeln('  phpx cs-fixer:3.26 fix src/                    # Alias with version');
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function getPharPath(string $pharName, string $version): string
    {
        $cacheDir = $this->getCacheDir();
        $pharBaseDir = $cacheDir . '/phars/' . basename($pharName, '.phar');
        return $pharBaseDir . '/' . $version . '/' . $pharName;
    }

    private function getCacheDir(): string
    {
        $baseDir = getenv('XDG_CACHE_HOME')
            ?: (getenv('HOME') . '/.cache');

        return $baseDir . '/phpx';
    }

    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 1) . ' ' . $units[$pow];
    }
}
