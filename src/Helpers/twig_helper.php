<?php

use KaleidPixel\Codeigniter4Twig\Config\Services;
use KaleidPixel\Codeigniter4Twig\Twig;

if (! function_exists('twig_instance')) {
    /**
     * load twig
     *
     * @return Twig
     */
    function twig_instance()
    {
        return Services::twig();
    }
}
