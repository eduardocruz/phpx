<?php

declare(strict_types=1);

namespace PHPX\Console;

use Symfony\Component\Console\Application as BaseApplication;
use PHPX\Console\Command\ExecuteCommand;
use PHPX\Console\Command\ListPharsCommand;
use PHPX\Console\Command\CacheSizeCommand;
use PHPX\Console\Command\CacheClearCommand;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('PHPX', '1.0.0');

        $this->add(new ExecuteCommand());
        $this->add(new ListPharsCommand());
        $this->add(new CacheSizeCommand());
        $this->add(new CacheClearCommand());
    }
}
