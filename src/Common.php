<?php

use Daycry\Twig\Config\Services;
use Daycry\Twig\Config\Twig as TwigConfig;

if (! function_exists('twig')) {
    function twig(string $name, array $data = [], ?TwigConfig $config = null): string
    {
        $renderer = Services::twig($config);

        return $renderer->render($name, $data);
    }
}
