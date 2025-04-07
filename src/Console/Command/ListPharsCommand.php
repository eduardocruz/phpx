<?php

declare(strict_types=1);

namespace PHPX\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
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

        $output->writeln('');
        $output->writeln('<info>Known PHAR files that can be executed directly:</info>');
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['PHAR Name', 'Available Versions']);

        foreach ($knownPhars as $name => $versions) {
            $versionList = implode(', ', array_keys($versions));
            $table->addRow([$name, $versionList]);
        }

        $table->render();

        $output->writeln('');
        $output->writeln('Usage examples:');
        $output->writeln('  phpx <phar-name>[:<version>] [arguments]');
        $output->writeln('');
        $output->writeln('For instance:');
        $output->writeln('  phpx php-cs-fixer.phar fix src/                  # Uses latest version');
        $output->writeln('  phpx php-cs-fixer.phar:3.26 fix src/            # Uses specific version');
        $output->writeln('  phpx phpunit.phar:9 --filter MyTest             # Uses PHPUnit 9.x');
        $output->writeln('');

        return Command::SUCCESS;
    }
}
