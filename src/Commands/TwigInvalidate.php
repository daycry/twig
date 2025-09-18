<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Twig\Config\Services;
use Daycry\Twig\Twig;

class TwigInvalidate extends BaseCommand
{
    protected $group       = 'Twig';
    protected $name        = 'twig:invalidate';
    protected $description = 'Invalidates (removes compiled cache for) a single Twig template.';
    protected $usage       = 'twig:invalidate <template> [--reinit]';
    protected $arguments   = [
        'template' => 'Logical template name without extension (e.g. layout/main or welcome)',
    ];
    protected $options = [
        '--reinit' => 'Recreate the Twig Environment if one or more cache files were removed.',
    ];

    public function run(array $params)
    {
        $template = $params[0] ?? null;
        if ($template === null) {
            CLI::error('Template logical name is required.');
            CLI::write('Usage: ' . $this->usage);

            return;
        }

        $reinit = in_array('--reinit', $params, true) || CLI::getOption('reinit');

        /** @var Twig $twig */
        $twig    = Services::twig();
        $removed = $twig->invalidateTemplate($template, $reinit);

        if ($removed === 0) {
            CLI::write("No cache files matched template '{$template}'.", 'yellow');

            return;
        }

        CLI::write("Removed {$removed} cache file(s) for template '{$template}'.", 'green');
        if ($reinit) {
            CLI::write('Twig Environment reinitialized.', 'green');
        }
    }
}
