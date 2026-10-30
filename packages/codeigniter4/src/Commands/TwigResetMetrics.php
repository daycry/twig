<?php

namespace Daycry\Twig\CodeIgniter4\Commands;

use CodeIgniter\CLI\CLI;

class TwigResetMetrics extends AbstractTwigCommand
{
    protected $name        = 'twig:reset-metrics';
    protected $description = 'Resets diagnostic metadata, optionally the compile index and compiled cache.';
    protected $usage       = 'twig:reset-metrics [--include-index] [--include-cache] [--json]';
    protected $options     = [
        '--include-index' => 'Also forget the compile index and source fingerprints.',
        '--include-cache' => 'Clear the compiled cache and all metadata through the configured backend.',
        '--json'          => 'Emit JSON output.',
    ];

    public function run(array $params)
    {
        $twig = $this->twig();
        if ($twig === null) {
            return EXIT_ERROR;
        }
        $payload = $twig->resetDiagnostics($this->flag('include-index', $params), $this->flag('include-cache', $params));
        if ($this->flag('json', $params)) {
            CLI::write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } elseif ($payload['ok']) {
            CLI::write('Twig diagnostic state reset.', 'green');
        } else {
            CLI::error('Unable to reset: ' . implode(', ', $payload['failed']));
        }

        return $payload['ok'] ? EXIT_SUCCESS : EXIT_ERROR;
    }
}
