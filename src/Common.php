<?php

use Daycry\Twig\Config\Services;

if (! function_exists('view')) {
    function view(string $name, array $data = []): string
    {
        $renderer = Services::twig();

        return $renderer->render($name, $data);
    }
}
