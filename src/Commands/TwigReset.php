<?php

namespace Daycry\Twig\Commands;

/**
 * Short alias for `twig:reset-metrics`.
 *
 * Inherits the full implementation; only command identity is overridden.
 */
class TwigReset extends TwigResetMetrics
{
    protected $name        = 'twig:reset';
    protected $description = 'Alias of twig:reset-metrics. Resets discovery stats, warmup summary and optionally compile index/cache.';
    protected $usage       = 'twig:reset [--include-index] [--include-cache] [--json]';
}
