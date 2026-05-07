<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\CLI;

/**
 * Shows the last persisted Twig warmup summary.
 */
class TwigWarmupStatus extends AbstractTwigCommand
{
    protected $name        = 'twig:warmup:status';
    protected $description = 'Displays the last warmup summary (from warmup-summary.json)';
    protected $usage       = 'twig:warmup:status [--json]';
    protected $options     = [
        '--json' => 'Emit JSON output.',
    ];

    public function run(array $params)
    {
        $asJson = $this->flag('json', $params);
        $twig   = $this->twig();
        if ($twig === null) {
            return EXIT_ERROR;
        }
        $diag   = $twig->getDiagnostics();
        $warmup = $diag['warmup'] ?? null;
        if ($asJson) {
            $payload = [
                'warmup' => $warmup,
                'ok'     => $warmup !== null,
            ];
            CLI::write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $warmup !== null ? EXIT_SUCCESS : EXIT_ERROR;
        }
        if ($warmup === null) {
            CLI::write('No warmup summary found.', 'yellow');

            return EXIT_ERROR;
        }
        $summary = $warmup['summary'] ?? [];
        $isAll   = ! empty($warmup['all']);
        $ts      = isset($warmup['timestamp']) ? date('c', (int) $warmup['timestamp']) : 'n/a';
        CLI::write('Warmup Summary (' . ($isAll ? 'all' : 'subset') . ') timestamp=' . $ts, 'green');
        CLI::write('  compiled: ' . ($summary['compiled'] ?? 0));
        CLI::write('  skipped : ' . ($summary['skipped'] ?? 0));
        CLI::write('  errors  : ' . ($summary['errors'] ?? 0));
        if (isset($summary['error_details']) && is_array($summary['error_details'])) {
            $details = $summary['error_details'];
            CLI::write('  error_details:', 'red');

            foreach ($details as $err) {
                if (! is_array($err)) {
                    continue;
                }
                CLI::write('    - ' . ($err['template'] ?? '?') . ': ' . ($err['error'] ?? ''), 'red');
            }
        }

        return EXIT_SUCCESS;
    }
}
