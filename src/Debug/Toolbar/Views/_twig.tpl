<?php declare(strict_types=1);
/**
 * Twig Debug Toolbar Panel Partial
 * Variables provided by Toolbar when rendering collector views:
 * - $collector (instance of Daycry\Twig\Debug\Toolbar\Collectors\Twig)
 * - $vars      (array of all collectors' var data)
 */

// Defensive: ensure collector present
$diag = [];
if (isset($vars['Twig Diagnostics']) && is_array($vars['Twig Diagnostics'])) {
    $diag = $vars['Twig Diagnostics'];
}

$section = function(string $title, array $pairs): string {
    $html = '<h4 style="margin:0.75rem 0 0.25rem;">' . esc($title) . '</h4>';
    $html .= '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
    foreach ($pairs as $k => $v) {
        if (is_array($v)) {
            $v = json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $html .= '<tr>'
            . '<td style="padding:2px 4px;border:1px solid #ccc;background:#f8f8f8;width:40%;"><strong>' . esc($k) . '</strong></td>'
            . '<td style="padding:2px 4px;border:1px solid #ccc;">' . esc((string)($v ?? '')) . '</td>'
            . '</tr>';
    }
    $html .= '</table>';
    return $html;
};

// Build section datasets safely
$core = [
    'Renders' => $diag['renders'] ?? 0,
    'Last View' => $diag['last_render_view'] ?? '',
    'Environment Resets' => $diag['environment_resets'] ?? 0,
];
$cache = [
    'Enabled' => isset($diag['cache']['enabled']) && $diag['cache']['enabled'] ? 'yes' : 'no',
    'Path' => $diag['cache']['path'] ?? '',
    'Compiled Templates' => $diag['cache']['compiled_templates'] ?? 'n/a',
];
$perf = [
    'Total Render (ms)' => $diag['performance']['total_render_time_ms'] ?? 0,
    'Avg Render (ms)' => $diag['performance']['avg_render_time_ms'] ?? 0,
];
$disc = [
    'Hits' => $diag['discovery']['hits'] ?? 0,
    'Misses' => $diag['discovery']['misses'] ?? 0,
    'Invalidations' => $diag['discovery']['invalidations'] ?? 0,
    'Cached' => isset($diag['discovery']['cached']) && $diag['discovery']['cached'] ? 'yes' : 'no',
    'Count' => $diag['discovery']['count'] ?? 'n/a',
];
$warm = [
    'Last Summary' => $diag['warmup']['summary'] ?? '-',
    'Last All' => isset($diag['warmup']['all']) ? ($diag['warmup']['all'] ? 'yes':'no') : '-',
];
$inv = [
    'Last' => $diag['invalidations']['last'] ?? '-',
    'Cumulative Removed' => $diag['invalidations']['cumulative_removed'] ?? 0,
];
$dyn = [
    'Functions (static/dynamic/pending)' => (($diag['static_functions']['configured'] ?? 0) . '/' . ($diag['dynamic_functions']['active'] ?? 0) . '/' . ($diag['dynamic_functions']['pending'] ?? 0)),
    'Filters (static/dynamic/pending)' => (($diag['static_filters']['configured'] ?? 0) . '/' . ($diag['dynamic_filters']['active'] ?? 0) . '/' . ($diag['dynamic_filters']['pending'] ?? 0)),
    'Extensions (configured/pending)' => (($diag['extensions']['configured'] ?? 0) . '/' . ($diag['extensions']['pending'] ?? 0)),
];
?>
<div class="ci-twig-panel" style="padding:0.5rem 0.75rem;">
    <h3 style="margin-top:0;">Twig Diagnostics</h3>
    <?= $section('Core', $core) ?>
    <?= $section('Cache', $cache) ?>
    <?= $section('Performance', $perf) ?>
    <?= $section('Discovery', $disc) ?>
    <?= $section('Warmup', $warm) ?>
    <?= $section('Invalidations', $inv) ?>
    <?= $section('Dynamics', $dyn) ?>
    <?php if (isset($diag['names'])): $n = $diag['names']; ?>
        <details style="margin:6px 0 0;">
            <summary style="cursor:pointer;font-size:11px;">Show function/filter names</summary>
            <?php
            $mk = static function(string $title, array $items): string {
                if (!$items) { return '<p style="margin:2px 0;font-size:11px;"><strong>'.esc($title).':</strong> (none)</p>'; }
                $html = '<p style="margin:4px 0 2px;font-size:11px;"><strong>'.esc($title).' ('.count($items).')</strong></p>';
                $html .= '<div style="max-height:120px;overflow:auto;border:1px solid #ccc;background:#fafafa;padding:2px 4px;font-size:11px;line-height:1.3;">';
                $html .= esc(implode(', ', $items));
                $html .= '</div>';
                return $html;
            };
            echo $mk('Static Functions', $n['static_functions'] ?? []);
            echo $mk('Dynamic Functions', $n['dynamic_functions'] ?? []);
            echo $mk('Static Filters', $n['static_filters'] ?? []);
            echo $mk('Dynamic Filters', $n['dynamic_filters'] ?? []);
            ?>
        </details>
    <?php endif; ?>
</div>
