<?php

namespace Daycry\Twig\Configuration;

use Closure;
use Twig\Cache\CacheInterface;

trait TwigOptions
{
    public string $extension = '.twig';

    /**
     * Optional custom cache directory for compiled Twig templates.
     * False disables compiled template caching.
     */
    public false|string $cachePath = false;

    /**
     * @var list<string> functions_safe
     */
    public array $functions_safe = [];

    /**
     * @var list<string> functions_asis
     */
    public array $functions_asis = [];

    /**
     * @var list<array<string,string>|string> paths
     *
     * A second parameter can be added to indicate the namespace of the view
     *
     * Example: public array $paths = [['/project/module1/views', 'module1'], '/project/module2/views'];
     *
     * For use templates inside Module1
     * $twig->render('@module1/view')
     *
     * OR
     *
     * For use templates inside Module2
     *
     * $twig->render('view')
     */
    public array $paths = [];

    /**
     * @var array<string,callable> filters
     */
    public array $filters = [];

    /**
     * @var list<string> extensions
     */
    public array $extensions = [];

    /**
     * When true, Twig will throw exceptions on undefined variables.
     * Mirrors Twig Environment option 'strict_variables'. Default false
     * to keep backward-compatible behavior.
     */
    public bool $strictVariables = false;

    /**
     * When false, the view method will clear the data between each
     * call. This keeps your data safe and ensures there is no accidental
     * leaking between calls, so you would need to explicitly pass the data
     * to each view. You might prefer to have the data stick around between
     * calls so that it is available to all views. If that is the case,
     * set $saveData to true.
     */
    public bool $saveData = true;

    // Discovery tuning flags removed: discoveryPersistList, discoveryPreload, discoveryUseAPCu, discoveryFingerprintMtimeDepth.
    // Behavior now derived automatically from leanMode + overrides (see enableDiscoverySnapshot, etc.).

    /**
     * Lean Mode: master switch to minimize work and persisted artifacts.
     * When true it disables by default:
     *  - Warmup summary persistence
     *  - Invalidation history
     *  - Extended dynamic metrics (name listings)
     *  - Extended diagnostics bundle
     *  - (Discovery snapshot) unless forced via override.
     * Individual (nullable) overrides may re-enable specific items without leaving Lean mode.
     * If leanMode = false the "full" profile enables all features by default. Nullable overrides = null mean
     * "use base profile".
     */
    public bool $leanMode = false;

    /**
     * Force (true/false) discovery snapshot regardless of profile.
     * null = base profile decision (lean? false : true)
     */
    public ?bool $enableDiscoverySnapshot = null;

    /**
     * Persist warmup summary. null = (lean? false : true)
     */
    public ?bool $enableWarmupSummary = null;

    /**
     * Persist and expose invalidation history. null = (lean? false : true)
     */
    public ?bool $enableInvalidationHistory = null;

    /**
     * Dynamic metrics (counts + names) for functions/filters. If false minimal counts (or zero) are shown.
     * null = (lean? false : true)
     */
    public ?bool $enableDynamicMetrics = null;

    /**
     * Extended diagnostics (static/dynamic name lists and non-essential details).
     * null = (lean? false : true)
     */
    public ?bool $enableExtendedDiagnostics = null;

    /**
     * Optional metadata TTL; remote compiled entries use a bounded retention when zero.
     */
    public int $cacheTtl = 0;

    public bool $debug                    = false;
    public string $cachePrefix            = 'twig_';
    public ?string $statePath             = null;
    public ?string $discoveryVersion      = null;
    public ?CacheInterface $compiledCache = null;
    public ?Closure $onWarmup             = null;
}
