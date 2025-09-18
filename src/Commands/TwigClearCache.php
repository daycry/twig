<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Twig\Config\Services;
use Daycry\Twig\Twig;

class TwigClearCache extends BaseCommand
{
    protected $group       = 'Twig';
    protected $name        = 'twig:clear-cache';
    protected $description = 'Clears the compiled Twig template cache.';
    protected $usage       = 'twig:clear-cache [--reinit]';
    protected $options     = [
        '--reinit' => 'Recreate the Twig Environment after clearing the cache.',
    ];

    public function run(array $params)
    {
        $reinit = in_array('--reinit', $params, true) || CLI::getOption('reinit');

        /** @var Twig $twig */
        $twig      = Services::twig();
        $cachePath = $twig->getCachePath();
        $removed   = $twig->clearCache($reinit);

        if ($removed === 0) {
            CLI::write('No cache files found in: ' . $cachePath, 'yellow');

            return;
        }

        CLI::write("Removed {$removed} file(s) from: {$cachePath}", 'green');
        if ($reinit) {
            CLI::write('Twig Environment reinitialized.', 'green');
        }
    }
}
