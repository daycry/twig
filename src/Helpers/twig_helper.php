<?php

use Daycry\Twig\Config\Services;
use Daycry\Twig\Twig;

if (! function_exists('twig_instance')) {
    /**
     * load twig
     */
    function twig_instance(): Twig
    {
        return Services::twig();
    }
}
