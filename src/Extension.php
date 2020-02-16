<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex;

use Bolt\Extension\BaseExtension;
use Symfony\Component\Routing\Route;

class Extension extends BaseExtension
{
    /**
     * Return the full name of the extension
     */
    public function getName(): string
    {
        return 'Conimex - Content Import & Export';
    }
}
