<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\CLI;
use InvalidArgumentException;

class TwigInvalidate extends AbstractTwigCommand
{
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
        if ($template === null || $template === '') {
            CLI::error('Template logical name is required.');
            CLI::write('Usage: ' . $this->usage);

            return EXIT_USER_INPUT;
        }

        $reinit = $this->flag('reinit', $params);
        $twig   = $this->twig();
        if ($twig === null) {
            return EXIT_ERROR;
        }

        try {
            $removed = $twig->invalidateTemplate($template, $reinit);
        } catch (InvalidArgumentException $e) {
            CLI::error('Invalid template name: ' . $e->getMessage());

            return EXIT_USER_INPUT;
        }

        if ($removed === 0) {
            CLI::write("No cache files matched template '{$template}'.", 'yellow');

            return EXIT_SUCCESS;
        }

        CLI::write("Removed {$removed} cache file(s) for template '{$template}'.", 'green');
        if ($reinit) {
            CLI::write('Twig Environment reinitialized.', 'green');
        }

        return EXIT_SUCCESS;
    }
}
