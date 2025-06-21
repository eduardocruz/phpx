<?php

declare(strict_types=1);

namespace PHPX\Console\Command;

use PHPX\Package\PackageManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;

class CacheClearCommand extends Command
{
    protected static $defaultName = 'cache:clear';
    protected static $defaultDescription = 'Clear the PHPX cache directory';

    protected function configure(): void
    {
        $this->setHelp('This command deletes all cached packages and PHARs from the PHPX cache directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packageManager = new PackageManager();
        $cacheDir = (new \ReflectionClass($packageManager))->getMethod('getCacheDir')->invoke($packageManager);

        if (!file_exists($cacheDir)) {
            $output->writeln("<info>Cache directory does not exist: $cacheDir</info>");

            return Command::SUCCESS;
        }

        // Calculate size before clearing
        $size = $this->getDirectorySize($cacheDir);
        $formattedSize = $this->formatSize($size);

        $output->writeln('');
        $output->writeln("<info>About to clear cache directory:</info> $cacheDir");
        $output->writeln("<info>Current cache size:</info> $formattedSize");
        $output->writeln('');

        // Ask for confirmation
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Are you sure you want to clear the cache? (y/n) ', false);

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<info>Operation cancelled.</info>');

            return Command::SUCCESS;
        }

        // Proceed with clearing the cache
        $output->writeln('Clearing cache...');
        $filesystem = new Filesystem();

        try {
            // Instead of removing the entire directory, we'll clear contents but keep the directory
            $subdirs = glob($cacheDir . '/*', GLOB_ONLYDIR);

            foreach ($subdirs as $dir) {
                $filesystem->remove($dir);
            }

            // Also remove any files in the root cache directory
            $files = glob($cacheDir . '/*.*');

            foreach ($files as $file) {
                $filesystem->remove($file);
            }

            $output->writeln('<info>Cache cleared successfully.</info>');
            $output->writeln("<info>Freed up:</info> $formattedSize");
        } catch (\Exception $e) {
            $output->writeln("<error>Error clearing cache: {$e->getMessage()}</error>");

            return Command::FAILURE;
        }

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
}
