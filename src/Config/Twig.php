<?php

namespace Daycry\Twig\Config;

use CodeIgniter\Config\BaseConfig;

class Twig extends BaseConfig
{
    public string $extension = '.twig';

    /**
     * Optional custom cache directory for compiled Twig templates.
     * If null, the library default WRITEPATH.'cache/twig' is used.
     */
    public string $cachePath = WRITEPATH . 'cache/twig';

    /**
     * @var list<string> functions_safe
     */
    public array $functions_safe = ['form_open', 'form_close', 'form_hidden', 'json_decode', 'form_error', 'set_value', 'csrf_field'];

    /**
     * @var list<string> functions_asis
     */
    public array $functions_asis = ['current_url', 'base_url', 'site_url'];

    /**
     * @var list<array<string,string>|string> paths
     *
     * A second parameter can be added to indicate the namespace of the view
     *
     * Example: public array $paths = [[APPPATH.'Module1/Views', 'module1'], APPPATH.'Module2/Views'];
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
     * @var array<string,array<mixed,string>|string> filters
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
     * @deprecated TTL is still honored if non-zero but auto mode typically uses no expiry (0). Will be simplified later.
     */
    public int $cacheTtl = 0;

    /**
     * Toolbar tuning: when many templates/functions or high traffic, the debug toolbar
     * panel can add overhead (name collection, template listing, json encoding, etc.).
     * These flags allow trimming what the collector does at runtime.
     */
    public bool $toolbarMinimal = false; // If true only core + cache + perf sections; skips discovery/warmup/invalidations/dynamics/template list/capabilities/persistence.

    public bool $toolbarShowTemplates    = true; // Show templates table (can be heavy if many templates)
    public int $toolbarMaxTemplates      = 50; // Hard cap for template rows
    public bool $toolbarShowCapabilities = true; // Show capabilities panel
    public bool $toolbarShowPersistence  = true; // Show persistence panel
}
