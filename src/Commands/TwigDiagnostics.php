<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Twig\Twig as TwigViewer;

class TwigDiagnostics extends BaseCommand
{
    protected $group       = 'Twig';
    protected $name        = 'twig:diagnostics';
    protected $description = 'Show current Twig diagnostics (renders, cache, discovery, dynamics, warmup).';
    protected $usage       = 'twig:diagnostics [--json]';
    protected $options     = [
        '--json' => 'Output diagnostics as JSON',
    ];

    public function run(array $params)
    {
        $asJson = in_array('--json', $params, true) || CLI::getOption('json');
        /** @var TwigViewer $viewer */
        $viewer = service('twig');
        $diag   = $viewer->getDiagnostics();
        if ($asJson) {
            CLI::write(json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }
        CLI::write('Twig Diagnostics');
        CLI::write(str_repeat('-', 60));
        $print = static function (string $label, $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            CLI::write(str_pad($label, 28) . ': ' . $value);
        };
        $print('Renders', $diag['renders'] ?? 0);
        $print('Last View', $diag['last_render_view'] ?? '');
        $print('Environment Resets', $diag['environment_resets'] ?? 0);
        if (isset($diag['cache'])) {
            $print('Cache Enabled', ($diag['cache']['enabled'] ?? false) ? 'yes' : 'no');
            $print('Cache Path', $diag['cache']['path'] ?? '');
            $print('Cache Backend', $diag['cache']['backend'] ?? 'file');
            $print('Compiled Templates', $diag['cache']['compiled_templates'] ?? 'n/a');
        }
        if (isset($diag['discovery'])) {
            $disc = $diag['discovery'];
            $print('Discovery Hits', $disc['hits'] ?? 0);
            $print('Discovery Misses', $disc['misses'] ?? 0);
            $print('Discovery Invalidations', $disc['invalidations'] ?? 0);
            $print('Discovery Count', $disc['count'] ?? 'n/a');
            $print('Discovery Persisted Count', $disc['persistedCount'] ?? 'n/a');
            $print('Discovery Cache Source', $disc['cache_source'] ?? 'n/a');
            if (isset($disc['persistence_medium'])) {
                $print('Discovery Medium', $disc['persistence_medium']);
            }
        }
        if (isset($diag['warmup'])) {
            $print('Warmup Summary', isset($diag['warmup']['summary']) ? json_encode($diag['warmup']['summary']) : '-');
            $print('Warmup All', isset($diag['warmup']['all']) ? ($diag['warmup']['all'] ? 'yes' : 'no') : '-');
        }
        if (isset($diag['persistence'])) {
            $pers = $diag['persistence'];
            if (isset($pers['warmup']['medium'])) {
                $print('Warmup Medium', $pers['warmup']['medium']);
            }
            if (isset($pers['compile_index']['medium'])) {
                $print('Compile Index Medium', $pers['compile_index']['medium']);
            }
            if (isset($pers['invalidations']['medium'])) {
                $print('Invalidations Medium', $pers['invalidations']['medium']);
            }
        }
        if (isset($diag['invalidations'])) {
            $print('Last Invalidation', $diag['invalidations']['last'] ?? '-');
            $print('Cumulative Invalidated', $diag['invalidations']['cumulative_removed'] ?? 0);
        }
        if (isset($diag['static_functions']) || isset($diag['dynamic_functions'])) {
            $print('Functions static/dynamic/pending', ($diag['static_functions']['configured'] ?? 0) . '/' . ($diag['dynamic_functions']['active'] ?? 0) . '/' . ($diag['dynamic_functions']['pending'] ?? 0));
        }
        if (isset($diag['static_filters']) || isset($diag['dynamic_filters'])) {
            $print('Filters static/dynamic/pending', ($diag['static_filters']['configured'] ?? 0) . '/' . ($diag['dynamic_filters']['active'] ?? 0) . '/' . ($diag['dynamic_filters']['pending'] ?? 0));
        }
        if (isset($diag['names'])) {
            $print('Names static functions', implode(', ', $diag['names']['static_functions'] ?? []));
            $print('Names dynamic functions', implode(', ', $diag['names']['dynamic_functions'] ?? []));
            $print('Names static filters', implode(', ', $diag['names']['static_filters'] ?? []));
            $print('Names dynamic filters', implode(', ', $diag['names']['dynamic_filters'] ?? []));
        }
        if (isset($diag['performance'])) {
            $print('Total Render ms', $diag['performance']['total_render_time_ms'] ?? 0);
            $print('Avg Render ms', $diag['performance']['avg_render_time_ms'] ?? 0);
        }
    }
}
