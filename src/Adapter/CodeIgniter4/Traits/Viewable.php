<?php

declare(strict_types=1);

namespace Daycry\Twig\CodeIgniter4\Traits;

use Daycry\Twig\CodeIgniter4\Config\Services;
use Daycry\Twig\CodeIgniter4\Config\Twig as TwigConfig;

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
