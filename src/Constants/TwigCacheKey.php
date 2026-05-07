<?php

namespace Daycry\Twig\Constants;

/**
 * Logical cache key suffixes used when persisting Twig artifacts to either the
 * filesystem (as `<dir>/<file>`) or the configured CI cache backend (as
 * `<prefix><suffix>`).
 *
 * Centralizing them avoids drift between the storage path and the cache-clear
 * path (e.g. `twig:reset` deleting different filenames than `Twig::saveX()`).
 */
enum TwigCacheKey: string
{
    case CompileIndex      = 'compile-index.json';
    case DiscoveryStats    = 'discovery-stats.json';
    case DiscoveryListSnap = 'discovery-stats-list.json';
    case WarmupSummary     = 'warmup-summary.json';
    case Invalidations     = 'invalidations.json';

    // Suffixes used against the CI cache backend (no `.json` extension).
    case CiCompileIndex   = 'compile.index';
    case CiDiscoveryStats = 'disc.stats';
    case CiDiscoveryList  = 'disc.list';
    case CiWarmupSummary  = 'warmup.summary';
    case CiInvalidations  = 'invalidations';
}
