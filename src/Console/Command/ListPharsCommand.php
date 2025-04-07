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
        $pharAliases = array_flip($packageManager->getPharAliases());

        $output->writeln('');
        $output->writeln('<info>Known PHAR files that can be executed directly:</info>');
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['PHAR Name', 'Available Versions', 'Aliases']);

        foreach ($knownPhars as $name => $versions) {
            $versionList = implode(', ', array_keys($versions));
            $aliases = isset($pharAliases[$name]) ? $pharAliases[$name] : '';
            $table->addRow([$name, $versionList, $aliases]);
        }

        $table->render();

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
}
