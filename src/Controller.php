<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex;

use Bolt\Extension\ExtensionController;
use Symfony\Component\HttpFoundation\Response;

class Controller extends ExtensionController
{
    public function index($name = 'foo'): Response
    {
        $context = [
            'title' => 'BobdenOtter Reference Extension',
            'name' => $name,
        ];

        return $this->render('@conimex/page.html.twig', $context);
    }
}
