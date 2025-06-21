<?php

declare(strict_types=1);

namespace PHPX\Console\Command;

use function Laravel\Prompts\{confirm, spin, table};

use PHPX\Package\PackageManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CacheSizeCommand extends Command
{
    protected static $defaultName = 'cache:size';
    protected static $defaultDescription = 'Show the size of the PHPX cache directory';

    protected function configure(): void
    {
        $this->setHelp('This command shows the total size of the PHPX cache directory and its subdirectories.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packageManager = new PackageManager();
        $cacheDir = (new \ReflectionClass($packageManager))->getMethod('getCacheDir')->invoke($packageManager);

        if (!file_exists($cacheDir)) {
            $output->writeln("<error>Cache directory does not exist: $cacheDir</error>");

            return Command::FAILURE;
        }

        // Calculate sizes with a spinner
        $rows = [];
        $totalSize = spin(function () use ($cacheDir, &$rows) {
            $total = $this->getDirectorySize($cacheDir);

            // Get all files recursively
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $size = $file->getSize();
                    $formattedSize = $this->formatSize($size);
                    // Get path relative to cache directory
                    $relativePath = substr($file->getPathname(), strlen($cacheDir) + 1);
                    $rows[] = [$formattedSize, $relativePath];
                }
            }

            // Sort rows by size (descending)
            usort($rows, function ($a, $b) {
                return $this->compareSizes($b[0], $a[0]);
            });

            return $total;
        }, 'Calculating cache sizes...');

        $formattedTotalSize = $this->formatSize($totalSize);

        $output->writeln('');
        $output->writeln("<info>PHPX Cache Directory:</info> $cacheDir");
        $output->writeln('');

        // Display the table using Laravel Prompts
        table(
            ['Size', 'File'],
            array_merge(
                $rows,
                [['---', '---']],
                [[$formattedTotalSize, 'TOTAL']]
            )
        );

        $output->writeln('');

        // Add confirmation prompt for cache clearing
        if (confirm('Would you like to clear the cache?')) {
            $output->writeln('To clear the cache, run: rm -rf ' . escapeshellarg($cacheDir));
        } else {
            $output->writeln('Cache left intact.');
        }
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function getDirectorySize(string $path): int
    {
        $size = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function formatSize(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    private function compareSizes(string $sizeA, string $sizeB): int
    {
        // Extract numeric values and units
        preg_match('/^([\d.]+)\s+(\w+)$/', $sizeA, $matchesA);
        preg_match('/^([\d.]+)\s+(\w+)$/', $sizeB, $matchesB);

        if (empty($matchesA) || empty($matchesB)) {
            return 0;
        }

        $valueA = (float) $matchesA[1];
        $unitA = $matchesA[2];

        $valueB = (float) $matchesB[1];
        $unitB = $matchesB[2];

        $units = ['B' => 0, 'KB' => 1, 'MB' => 2, 'GB' => 3, 'TB' => 4];

        // Compare units first
        if ($unitA !== $unitB) {
            return $units[$unitA] <=> $units[$unitB];
        }

        // If units are the same, compare values
        return $valueA <=> $valueB;
    }
}
