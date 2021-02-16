<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex;

use BobdenOtter\Conimex\Widget\ExportWidget;
use Bolt\Extension\BaseExtension;

class Extension extends BaseExtension
{
    /**
     * Return the full name of the extension
     */
    public function getName(): string
    {
        return 'Conimex - Content Import & Export';
    }

    public function initialize(): void
    {
        $this->addTwigNamespace('conimex');
        $this->addWidget(new ExportWidget());
    }
}
