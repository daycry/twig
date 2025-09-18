<?php

namespace Daycry\Twig\Debug\Toolbar\Collectors;

use CodeIgniter\Debug\Toolbar\Collectors\BaseCollector;
use Daycry\Twig\Config\Services;
use Daycry\Twig\Twig as twigLibrary;
use Throwable;

/**
 * Twigs collector
 */
class Twig extends BaseCollector
{
    /**
     * Whether this collector has data that can
     * be displayed in the Timeline.
     *
     * @var bool
     */
    protected $hasTimeline = true;

    /**
     * Whether this collector needs to display
     * content in a tab or not.
     *
     * @var bool
     */
    // Tab content enabled; custom view partial provided at package path.
    protected $hasTabContent = true;

    /**
     * Whether this collector needs to display
     * a label or not.
     *
     * @var bool
     */
    protected $hasLabel = true;

    /**
     * Whether this collector has data that
     * should be shown in the Vars tab.
     *
     * @var bool
     */
    protected $hasVarData = true;

    /**
     * The 'title' of this Collector.
     * Used to name things in the toolbar HTML.
     *
     * @var string
     */
    protected $title = 'Twig';

    /**
     * Instance of the shared Renderer service
     *
     * @var twigLibrary|null
     */
    protected $viewer;

    /**
     * Views counter
     *
     * @var array
     */
    protected $views = [];

    private function initViewer(): void
    {
        $this->viewer ??= Services::twig();
    }

    /**
     * Child classes should implement this to return the timeline data
     * formatted for correct usage.
     */
    protected function formatTimelineData(): array
    {
        $this->initViewer();

        $data = [];
        $rows = $this->viewer->getPerformanceData();

        foreach ($rows as $info) {
            $data[] = [
                'name'      => 'View: ' . $info['view'],
                'component' => 'Views',
                'start'     => $info['start'],
                'duration'  => $info['end'] - $info['start'],
            ];
        }

        return $data;
    }

    /**
     * Gets a collection of data that should be shown in the 'Vars' tab.
     * The format is an array of sections, each with their own array
     * of key/value pairs:
     *
     *  $data = [
     *      'section 1' => [
     *          'foo' => 'bar,
     *          'bar' => 'baz'
     *      ],
     *      'section 2' => [
     *          'foo' => 'bar,
     *          'bar' => 'baz'
     *      ],
     *  ];
     */
    public function getVarData(): array
    {
        $this->initViewer();
        $vars = [
            'View Data' => $this->viewer->getData(),
        ];
        if (method_exists($this->viewer, 'getDiagnostics')) {
            $vars['Twig Diagnostics'] = $this->viewer->getDiagnostics();
        }

        return $vars;
    }

    /**
     * Returns a count of all views.
     */
    public function getBadgeValue(): int
    {
        $this->initViewer();
        if (method_exists($this->viewer, 'getDiagnostics')) {
            $diag = $this->viewer->getDiagnostics();

            return $diag['renders'] ?? count($this->viewer->getPerformanceData());
        }

        return count($this->viewer->getPerformanceData());
    }

    /**
     * Display the icon.
     *
     * Icon from https://icons8.com - 1em package
     */
    public function icon(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAADeSURBVEhL7ZSxDcIwEEWNYA0YgGmgyAaJLTcUaaBzQQEVjMEabBQxAdw53zTHiThEovGTfnE/9rsoRUxhKLOmaa6Uh7X2+UvguLCzVxN1XW9x4EYHzik033Hp3X0LO+DaQG8MDQcuq6qao4qkHuMgQggLvkPLjqh00ZgFDBacMJYFkuwFlH1mshdkZ5JPJERA9JpI6xNCBESvibQ+IURA9JpI6xNCBESvibQ+IURA9DTsuHTOrVFFxixgB/eUFlU8uKJ0eDBFOu/9EvoeKnlJS2/08Tc8NOwQ8sIfMeYFjqKDjdU2sp4AAAAASUVORK5CYII=';
    }

    /**
     * Returns HTML for the tab content when selected in Debug Toolbar.
     */
    public function tabContent(): string
    {
        $this->initViewer();
        if (! method_exists($this->viewer, 'getDiagnostics')) {
            return '<div class="ci-twig-panel"><p>No diagnostics available.</p></div>';
        }
        // To ensure discovery hit/miss counts include the template listing (if shown),
        // we prefetch the template list (at most once) BEFORE capturing diagnostics.
        $templatesData      = null;
        $withStatusForPanel = true;
        if (method_exists($this->viewer, 'listTemplates')) {
            try {
                $templatesData = $this->viewer->listTemplates($withStatusForPanel);
            } catch (Throwable $e) {
                $templatesData = null;
            }
        }
        // Now capture diagnostics after potential listTemplates() which may increment hits.
        $diag = $this->viewer->getDiagnostics();
        $html = '<div class="ci-twig-panel" style="padding:0.5rem 0.75rem;">';
        $html .= '<h3 style="margin-top:0;">Twig Diagnostics</h3>';
        $json = static function ($v): string {
            try {
                return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—';
            } catch (Throwable $e) {
                return '—';
            }
        };
        $sections = [
            'Core' => [
                'Renders'            => $diag['renders'] ?? 0,
                'Last View'          => htmlspecialchars((string) ($diag['last_render_view'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'Environment Resets' => $diag['environment_resets'] ?? 0,
            ],
            'Cache' => [
                'Enabled'            => ($diag['cache']['enabled'] ?? false) ? 'yes' : 'no',
                'Path'               => htmlspecialchars((string) ($diag['cache']['path'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'Compiled Templates' => $diag['cache']['compiled_templates'] ?? 'n/a',
            ],
            'Performance' => [
                'Total Render (ms)' => $diag['performance']['total_render_time_ms'] ?? 0,
                'Avg Render (ms)'   => $diag['performance']['avg_render_time_ms'] ?? 0,
            ],
            'Discovery' => (static function () use ($diag) {
                $d           = $diag['discovery'] ?? [];
                $fingerprint = $d['fingerprint'] ?? null;
                if (is_string($fingerprint) && strlen($fingerprint) > 16) {
                    $fingerprint = substr($fingerprint, 0, 16) . '…';
                }

                return [
                    'Hits (cache reuse)' => $d['hits'] ?? 0,
                    'Misses (scans)'     => $d['misses'] ?? 0,
                    'Invalidations'      => $d['invalidations'] ?? 0,
                    'In-Memory Cached'   => ($d['cached'] ?? false) ? 'yes' : 'no',
                    'Count (current)'    => $d['count'] ?? 'n/a',
                    'Persisted Count'    => $d['persistedCount'] ?? 'n/a',
                    'Cache Source'       => $d['cache_source'] ?? 'n/a',
                    'Fingerprint'        => $fingerprint ?? 'n/a',
                ];
            })(),
            'Warmup' => [
                'Last Summary' => isset($diag['warmup']['summary']) ? $json($diag['warmup']['summary']) : '—',
                'Last All'     => isset($diag['warmup']['all']) ? ($diag['warmup']['all'] ? 'yes' : 'no') : '—',
            ],
            'Invalidations' => [
                'Last'               => isset($diag['invalidations']['last']) ? $json($diag['invalidations']['last']) : '—',
                'Cumulative Removed' => $diag['invalidations']['cumulative_removed'] ?? 0,
            ],
            'Dynamics' => [
                'Functions (static/dynamic/pending)' => ($diag['static_functions']['configured'] ?? 0) . '/' . ($diag['dynamic_functions']['active'] ?? 0) . '/' . ($diag['dynamic_functions']['pending'] ?? 0),
                'Filters (static/dynamic/pending)'   => ($diag['static_filters']['configured'] ?? 0) . '/' . ($diag['dynamic_filters']['active'] ?? 0) . '/' . ($diag['dynamic_filters']['pending'] ?? 0),
                'Extensions (configured/pending)'    => ($diag['extensions']['configured'] ?? 0) . '/' . ($diag['extensions']['pending'] ?? 0),
            ],
        ];

        foreach ($sections as $label => $pairs) {
            $html .= '<h4 style="margin:0.75rem 0 0.25rem;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</h4>';
            $html .= '<table style="width:100%;border-collapse:collapse;font-size:12px;">';

            foreach ($pairs as $k => $v) {
                $html .= '<tr>'
                      . '<td style="padding:2px 4px;border:1px solid #ccc;background:#f8f8f8;width:40%;"><strong>' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '</strong></td>'
                      . '<td style="padding:2px 4px;border:1px solid #ccc;">' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '</td>'
                      . '</tr>';
            }
            $html .= '</table>';
            if ($label === 'Dynamics' && isset($diag['names'])) {
                $n  = $diag['names'];
                $mk = static function (string $title, array $items): string {
                    if (! $items) {
                        return '<p style="margin:2px 0;font-size:11px;"><strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ':</strong> (none)</p>';
                    }
                    $html = '<p style="margin:4px 0 2px;font-size:11px;"><strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' (' . count($items) . ')</strong></p>';
                    $html .= '<div style="max-height:120px;overflow:auto;border:1px solid #ccc;background:#fafafa;padding:2px 4px;font-size:11px;line-height:1.3;">';
                    $html .= htmlspecialchars(implode(', ', $items), ENT_QUOTES, 'UTF-8');
                    $html .= '</div>';

                    return $html;
                };
                $html .= '<details style="margin:6px 0 0;">';
                $html .= '<summary style="cursor:pointer;font-size:11px;">Show function/filter names</summary>';
                $html .= $mk('Static Functions', $n['static_functions'] ?? []);
                $html .= $mk('Dynamic Functions', $n['dynamic_functions'] ?? []);
                $html .= $mk('Static Filters', $n['static_filters'] ?? []);
                $html .= $mk('Dynamic Filters', $n['dynamic_filters'] ?? []);
                $html .= '</details>';
            }
        }
        // Templates panel (similar idea to Symfony's) - show up to 50 entries to avoid overload
        if ($templatesData !== null && ! empty($templatesData)) {
            $html .= '<h4 style="margin:0.75rem 0 0.25rem;">Templates</h4>';
            $html .= '<table style="width:100%;border-collapse:collapse;font-size:11px;">';
            $html .= '<tr><th style="text-align:left;padding:2px 4px;border:1px solid #ccc;background:#eee;">Name</th><th style="text-align:left;padding:2px 4px;border:1px solid #ccc;background:#eee;">Compiled</th></tr>';
            $limit         = 50;
            $count         = 0;
            $compiledTotal = 0;
            $total         = count($templatesData);

            foreach ($templatesData as $row) {
                // Row may be string if called without status
                if (is_string($row)) {
                    $name = $row;
                    $comp = null;
                } else {
                    $name = $row['name'] ?? '';
                    $comp = ! empty($row['compiled']);
                }
                if ($comp === true) {
                    $compiledTotal++;
                }
                $html .= '<tr>'
                      . '<td style="padding:2px 4px;border:1px solid #ccc;">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td>'
                      . '<td style="padding:2px 4px;border:1px solid #ccc;">' . ($comp === null ? 'n/a' : ($comp ? 'yes' : 'no')) . '</td>'
                      . '</tr>';
                $count++;
                if ($count >= $limit) {
                    break;
                }
            }
            $html .= '</table>';
            $extra = $total - $count;
            $html .= '<p style="margin:4px 0 0;font-size:11px;color:#555;">Showing ' . $count . ' of ' . $total . ' templates; compiled=' . $compiledTotal . ($extra > 0 ? ' (+' . $extra . ' more not shown)' : '') . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * CI4 calls display() when assembling tab content if hasTabContent=true.
     * We override to return same HTML as tabContent without requiring a physical view file.
     */
    public function display(): string
    {
        return $this->tabContent();
    }
}
