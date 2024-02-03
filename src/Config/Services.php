<?php

namespace KaleidPixel\Codeigniter4Twig\Config;

use CodeIgniter\Config\BaseService;
use KaleidPixel\Codeigniter4Twig\Config\Twig as TwigConfig;
use KaleidPixel\Codeigniter4Twig\Twig;

class Services extends BaseService
{
    public static function twig(?TwigConfig $config = null, bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('twig', $config);
        }

        $config ??= config('Twig');

        return new Twig($config);
    }
}
