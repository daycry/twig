<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Twig\Config\Services;
use Daycry\Twig\Twig;

class TwigBatchInvalidate extends BaseCommand
{
    protected $group       = 'Twig';
    protected $name        = 'twig:invalidate:batch';
    protected $description = 'Invalidates multiple Twig templates at once.';
    protected $usage       = 'twig:invalidate:batch <template1> <template2> ... [--reinit]';
    protected $arguments   = [
        'templates' => 'List of logical template names (without extension)',
    ];
    protected $options = [
        '--reinit' => 'Recreate the Twig Environment if any cache files were removed.',
    ];

    public function run(array $params)
    {
        $reinit    = in_array('--reinit', $params, true) || CLI::getOption('reinit');
        $templates = array_values(array_filter($params, static fn ($p) => ! str_starts_with($p, '--')));
        if (empty($templates)) {
            CLI::error('Provide at least one template logical name.');

            return;
        }
        /** @var Twig $twig */
        $twig   = Services::twig();
        $result = $twig->invalidateTemplates($templates, $reinit);
        if ($result['removed'] === 0) {
            CLI::write('No cache files removed.', 'yellow');

            return;
        }

        foreach ($result['templates'] as $name => $count) {
            CLI::write($name . ': removed ' . $count);
        }
        CLI::write('Total removed: ' . $result['removed'], 'green');
        if ($result['reinit']) {
            CLI::write('Twig Environment reinitialized.', 'green');
        }
    }
}
