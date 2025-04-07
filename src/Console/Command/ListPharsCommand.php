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
        $table->setHeaders(['PHAR Name', 'Source URL']);

        foreach ($knownPhars as $name => $url) {
            $table->addRow([$name, $url]);
        }

        $table->render();

        $output->writeln('');
        $output->writeln('Usage example:');
        $output->writeln('  phpx <phar-name> [arguments]');
        $output->writeln('');
        $output->writeln('For instance:');
        $output->writeln('  phpx php-cs-fixer.phar fix src/');
        $output->writeln('');

        return Command::SUCCESS;
    }
} 