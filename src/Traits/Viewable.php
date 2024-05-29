<?php

declare(strict_types=1);

namespace Daycry\Twig\Traits;

use Config\Services;
use Daycry\Twig\Config\Twig as TwigConfig;

trait Viewable
{
    /**
     * Provides a way for third-party systems to simply override
     * the way the view gets converted to HTML to integrate with their
     * own templating systems.
     */
    protected function view(string $view, array $data = [], ?TwigConfig $config = null): string
    {
        $renderer = Services::twig($config);

        return $renderer->render($view, $data);
    }
}
