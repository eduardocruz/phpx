<?php

declare(strict_types=1);

namespace PHPX\Console\Command;

use PHPX\Package\PackageManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PharVersionsCommand extends Command
{
    protected static $defaultName = 'phar-versions';
    protected static $defaultDescription = 'Show help about PHAR version specification';

    protected function configure(): void
    {
        $this->setHelp('This command shows how to use version specification with PHAR files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packageManager = new PackageManager();
        $knownPhars = $packageManager->getKnownPhars();

        $output->writeln('');
        $output->writeln('<info>PHAR Version Specification</info>');
        $output->writeln('');
        $output->writeln('PHPX supports specifying versions for PHAR files using the following syntax:');
        $output->writeln('  phpx <phar-name>:<version> [arguments]');
        $output->writeln('');

        $output->writeln('<info>Examples:</info>');
        $output->writeln('  phpx php-cs-fixer.phar fix src/                  # Latest version (default)');
        $output->writeln('  phpx php-cs-fixer.phar:3.26 fix src/            # Specific version');
        $output->writeln('  phpx phpunit.phar:9 --filter MyTest             # Major version');
        $output->writeln('  phpx composer.phar:2 install                    # Major version');
        $output->writeln('');

        $output->writeln('<info>Available PHARs and their versions:</info>');
        $output->writeln('');

        foreach ($knownPhars as $name => $versions) {
            $output->writeln("  <comment>$name</comment>");

            foreach ($versions as $version => $url) {
                $output->writeln("    - $version");
            }
            $output->writeln('');
        }

        $output->writeln('Each version is cached separately in:');
        $output->writeln('  ~/.cache/phpx/phars/<phar-name>/<version>/');
        $output->writeln('');

        return Command::SUCCESS;
    }
}
