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
    public ?string $cachePath = null;

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

    /**
     * When true, the discovery service will persist also the full list of templates
     * (not only counters) alongside a fingerprint. On a subsequent request, if the
     * fingerprint matches, the in-memory cache can be restored without scanning.
     */
    public bool $discoveryPersistList = false;

    /**
     * When true (and discoveryPersistList enabled), the template list is eagerly
     * loaded from the persisted snapshot during diagnostics/list operations if
     * no in-process cache exists and fingerprint matches.
     */
    public bool $discoveryPreload = false;

    /**
     * Use APCu (if extension loaded and enabled) to cache the discovered list
     * across processes. Falls back gracefully to filesystem JSON snapshot.
     */
    public bool $discoveryUseAPCu = false;

    /**
     * Depth for directory mtime sampling in fingerprint calculation.
     * 0 = only root template directories; 1 = include immediate subdirectories, etc.
     * Higher values increase fingerprint accuracy at cost of extra stat() calls.
     */
    public int $discoveryFingerprintMtimeDepth = 0;

    /**
     * Backend used for Twig compiled template cache.
     *  - 'file' (default): filesystem path in $cachePath (or default WRITEPATH/cache/twig)
     *  - 'ci' : use the CodeIgniter cache service (redis, memcached, etc. as configured)
     */
    public string $cacheBackend = 'file';

    /**
     * Prefix for CI cache backend keys (only used when cacheBackend = 'ci').
     * If null, the library will derive it from Config\Cache::$prefix concatenated with 'twig_'.
     * Example: if Config\Cache::$prefix = 'app_', final prefix becomes 'app_twig_'.
     */
    public ?string $cachePrefix = null;

    /**
     * TTL seconds for CI cache entries (0 = no expiry).
     */
    public int $cacheTtl = 0;
}
