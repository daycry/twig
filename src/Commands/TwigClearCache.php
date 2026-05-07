<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\CLI;

class TwigClearCache extends AbstractTwigCommand
{
    protected $name        = 'twig:clear-cache';
    protected $description = 'Clears the compiled Twig template cache.';
    protected $usage       = 'twig:clear-cache [--reinit]';
    protected $options     = [
        '--reinit' => 'Recreate the Twig Environment after clearing the cache.',
    ];

    public function run(array $params)
    {
        $reinit = $this->flag('reinit', $params);

        $twig = $this->twig();
        if ($twig === null) {
            return EXIT_ERROR;
        }
        $cachePath = $twig->getCachePath();
        $removed   = $twig->clearCache($reinit);

        if ($removed === 0) {
            CLI::write('No cache files found in: ' . $cachePath, 'yellow');

            return EXIT_SUCCESS;
        }

        CLI::write("Removed {$removed} file(s) from: {$cachePath}", 'green');
        if ($reinit) {
            CLI::write('Twig Environment reinitialized.', 'green');
        }

        return EXIT_SUCCESS;
    }
}
