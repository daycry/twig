<?php

use Daycry\Twig\Config\Services;

if (! function_exists('twig_instance')) {
    /**
     * load twig
     *
     * @return \Daycry\Twig\Twig
     */
    function twig_instance()
    {
        return Services::twig();
    }
}
