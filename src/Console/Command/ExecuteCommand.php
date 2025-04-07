<?php

declare(strict_types=1);

namespace PHPX\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PHPX\Package\PackageManager;
use PHPX\Package\ExecutionEnvironment;

class ExecuteCommand extends Command
{
    protected static $defaultName = 'execute';
    protected static $defaultDescription = 'Execute a PHP package without installing it globally';

    protected function configure(): void
    {
        $this
            ->addArgument(
                'package',
                InputArgument::REQUIRED,
                'The package to execute (format: vendor/package[:version] or package.phar)'
            )
            ->addArgument(
                'args',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Arguments to pass to the package'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packageSpec = $input->getArgument('package');
        $args = $input->getArgument('args');

        try {
            $packageManager = new PackageManager();
            $package = $packageManager->resolvePackage($packageSpec);

            $environment = new ExecutionEnvironment($package);
            return $environment->execute($args);
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
