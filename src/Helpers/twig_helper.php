<?php

use Daycry\Twig\Twig;

if (! function_exists('twig_instance')) {
    /**
     * load twig
     *
     * @return Daycry\Twig\Twig
     */
    function twig_instance()
    {
        return \Daycry\Twig\Config\Services::twig();
    }
}
