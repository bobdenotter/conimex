<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex;

use Bolt\Widget\Injector\RequestZone;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class EventListener
{
    public function handleEvent(ResponseEvent $event): void
    {
        if (! RequestZone::isForFrontend($event->getRequest())) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();
        $content .= "\n<!-- It works! -->\n";

        $e->getResponse()->setContent($content);
    }
}
