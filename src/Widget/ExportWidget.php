<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex\Widget;

use Bolt\Widget\BaseWidget;
use Bolt\Widget\Injector\AdditionalTarget;
use Bolt\Widget\Injector\RequestZone;
use Bolt\Widget\TwigAwareInterface;

class ExportWidget extends BaseWidget implements TwigAwareInterface
{
    protected $name = 'Content Export';
    protected $target = AdditionalTarget::WIDGET_BACK_OVERVIEW_ASIDE_MIDDLE;
    protected $zone = RequestZone::BACKEND;
    protected $template = '@conimex/contenttype_export_widget.html.twig';
}
