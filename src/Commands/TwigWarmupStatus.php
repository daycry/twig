<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Twig\Config\Services;
use Daycry\Twig\Twig;

/**
 * Shows the last persisted Twig warmup summary.
 */
class TwigWarmupStatus extends BaseCommand
{
    protected $group       = 'Twig';
    protected $name        = 'twig:warmup:status';
    protected $description = 'Displays the last warmup summary (from warmup-summary.json)';
    protected $usage       = 'twig:warmup:status [--json]';
    protected $options     = [
        '--json' => 'Emit JSON output.',
    ];

    public function run(array $params)
    {
        $asJson = in_array('--json', $params, true) || CLI::getOption('json');
        /** @var Twig $twig */
        $twig = Services::twig();
        // Force diagnostics load (which loads persisted warmup if present)
        $diag   = method_exists($twig, 'getDiagnostics') ? $twig->getDiagnostics() : null;
        $warmup = $diag['warmup'] ?? null;
        if ($asJson) {
            $payload = [
                'warmup' => $warmup,
                'ok'     => $warmup !== null,
            ];
            CLI::write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return;
        }
        if ($warmup === null) {
            CLI::write('No warmup summary found.', 'yellow');

            return;
        }
        $summary = $warmup['summary'] ?? [];
        $isAll   = ! empty($warmup['all']);
        $ts      = isset($warmup['timestamp']) ? date('c', (int) $warmup['timestamp']) : 'n/a';
        CLI::write('Warmup Summary (' . ($isAll ? 'all' : 'subset') . ') timestamp=' . $ts, 'green');
        CLI::write('  compiled: ' . ($summary['compiled'] ?? 0));
        CLI::write('  skipped : ' . ($summary['skipped'] ?? 0));
        CLI::write('  errors  : ' . ($summary['errors'] ?? 0));
        if (isset($summary['error_details']) && is_array($summary['error_details'])) {
            $details = (array) $summary['error_details'];
            CLI::write('  error_details:', 'red');

            foreach ($details as $err) {
                if (! is_array($err)) {
                    continue;
                }
                CLI::write('    - ' . ($err['template'] ?? '?') . ': ' . ($err['error'] ?? ''), 'red');
            }
        }
    }
}
