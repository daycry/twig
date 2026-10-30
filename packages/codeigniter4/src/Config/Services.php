<?php

namespace Daycry\Twig\CodeIgniter4\Config;

use CodeIgniter\Config\BaseService;
use Daycry\Twig\CodeIgniter4\Config\Twig as TwigConfig;
use Daycry\Twig\CodeIgniter4\Twig;

class Services extends BaseService
{
    public static function twig(?TwigConfig $config = null, bool $getShared = true): Twig
    {
        if ($getShared) {
            return static::getSharedInstance('twig', $config);
        }

        $config ??= config('Twig');

        return new Twig($config);
    }
}
