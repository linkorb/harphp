<?php

declare(strict_types=1);

namespace HarPhp;

use HarPhp\Command\CatCommand;
use HarPhp\Command\ListCommand;
use HarPhp\Command\ViewCommand;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public const VERSION = '1.0.0';

    public function __construct(
        private readonly string $baseDir
    ) {
        parent::__construct('harphp', self::VERSION);

        $this->add(new CatCommand($this->baseDir));
        $this->add(new ListCommand($this->baseDir));
        $this->add(new ViewCommand($this->baseDir));
    }
}
