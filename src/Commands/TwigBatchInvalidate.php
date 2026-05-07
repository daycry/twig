<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\CLI;
use InvalidArgumentException;

class TwigBatchInvalidate extends AbstractTwigCommand
{
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
        $reinit    = $this->flag('reinit', $params);
        $templates = $this->positional($params);
        if (empty($templates)) {
            CLI::error('Provide at least one template logical name.');

            return EXIT_USER_INPUT;
        }
        $twig = $this->twig();
        if ($twig === null) {
            return EXIT_ERROR;
        }

        try {
            $result = $twig->invalidateTemplates($templates, $reinit);
        } catch (InvalidArgumentException $e) {
            CLI::error('Invalid template name: ' . $e->getMessage());

            return EXIT_USER_INPUT;
        }
        if ($result['removed'] === 0) {
            CLI::write('No cache files removed.', 'yellow');

            return EXIT_SUCCESS;
        }

        foreach ($result['templates'] as $name => $count) {
            CLI::write($name . ': removed ' . $count);
        }
        CLI::write('Total removed: ' . $result['removed'], 'green');
        if ($result['reinit']) {
            CLI::write('Twig Environment reinitialized.', 'green');
        }

        return EXIT_SUCCESS;
    }
}
