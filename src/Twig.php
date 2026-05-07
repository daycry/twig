<?php

namespace Daycry\Twig;

use CodeIgniter\Filters\DebugToolbar;
use Config\Services;
use Config\Toolbar;
use Daycry\Twig\Cache\CICacheAdapter;
use Daycry\Twig\Cache\TemplateCacheManager;
use Daycry\Twig\Config\CapabilitiesProfile;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Debug\Toolbar\Collectors\Twig as CollectorsTwig;
use Daycry\Twig\Discovery\TemplateDiscovery;
use Daycry\Twig\Invalidation\TemplateInvalidator;
use Daycry\Twig\Logging\TwigLogger;
use Daycry\Twig\Profile\RenderProfiler;
use Daycry\Twig\Registry\DynamicRegistry;
use Daycry\Twig\Support\PersistenceDecoder;
use Daycry\Twig\Support\TemplateNameValidator;
use FilesystemIterator;
// (LoggerInterface import removed — logging now uses global log_message helper only)
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Class General
 */
class Twig
{
    /**
     * Saved Data.
     */
    protected array $data = [];

    protected string $extension = '.twig';

    /**
     * @var array Paths to Twig templates
     */
    private array $paths = [APPPATH . 'Views'];

    /**
     * @var array Functions to add to Twig
     */
    private array $functions_asis = ['base_url', 'site_url'];

    /**
     * @var array Functions with `is_safe` option
     *
     * @see http://twig.sensiolabs.org/doc/advanced.html#automatic-escaping
     */
    private array $functions_safe = [
        'form_open', 'form_close', 'form_error', 'form_hidden', 'set_value',
    ];

    /**
     * @var array<string,array<mixed,string>|string> filters
     */
    private array $filters = [];

    /**
     * @var array Twig Environment Options
     *
     * @see http://twig.sensiolabs.org/doc/api.html#environment-options
     */
    private array $config = [];

    /**
     * https://twig.symfony.com/doc/3.x/advanced.html
     */
    private array $extensions = [];

    /**
     * Extensions queued before the Environment is created or manually registered after creation.
     *
     * @var list<class-string<ExtensionInterface>>
     */
    private array $pendingExtensions = [];

    /**
     * @var bool Whether functions are added or not
     */
    private bool $functions_added = false;

    private ?Environment $twig = null;

    /**
     * @class \Twig\Loader\FilesystemLoader
     */
    private ?LoaderInterface $loader = null;

    protected array $performanceData = [];
    protected bool $debug            = false;
    protected bool $saveData         = true;
    protected ?array $tempData       = null;
    protected int $viewsCount        = 0;

    // Diagnostics / instrumentation counters
    private int $renderCount           = 0;
    private int $environmentResets     = 0;
    private float $totalRenderTime     = 0.0;
    private ?array $lastWarmup         = null; // ['summary'=>array,'all'=>bool,'timestamp'=>float]
    private ?array $lastInvalidation   = null; // ['type'=>string,'removed'=>int,'reinit'=>bool,'timestamp'=>float]
    private int $cumulativeInvalidated = 0;

    /**
     * Capability profile derivado de configuración (leanMode + overrides).
     * Claves:
     *  - discoverySnapshot
     *  - warmupSummary
     *  - invalidationHistory
     *  - dynamicMetrics
     *  - extendedDiagnostics
     */
    private array $capabilities = [
        'discoverySnapshot'   => false,
        'warmupSummary'       => true,
        'invalidationHistory' => true,
        'dynamicMetrics'      => true,
        'extendedDiagnostics' => true,
    ];

    // (No internal logger instance retained)
    /**
     * Cache manager for compiled templates & index
     */
    private TemplateCacheManager $cacheManager;

    /**
     * Dynamic registry extracted (Stage3)
     */
    private readonly DynamicRegistry $dynamicRegistry;

    /**
     * @var array<string,bool|string> namespace specific autoescape strategies (namespace without @)
     */
    private array $autoescapeNamespaceMap = [];

    /**
     * In-process cache of discovered logical template names.
     */
    // Removed: handled by TemplateDiscovery service
    private readonly TemplateDiscovery $discovery;
    private readonly TemplateInvalidator $invalidator;

    // Cache backend: always attempt service('cache'). If handler is File* treat as filesystem;
    // otherwise wrap with CICacheAdapter. Legacy string backend replaced by boolean flag.
    private bool $usingCacheService = false; // true when non-file cache handler in use
    private ?string $cachePrefix    = null;  // resolved during initialize
    private int $cacheTtl           = 0;
    private readonly TwigLogger $log;
    private readonly RenderProfiler $renderProfiler;

    public function __construct(?TwigConfig $config = null, ?LoggerInterface $logger = null)
    {
        $this->log             = new TwigLogger($logger);
        $this->renderProfiler  = new RenderProfiler();
        $this->discovery       = new TemplateDiscovery();
        $this->cacheManager    = new TemplateCacheManager($this->extension);
        $this->dynamicRegistry = new DynamicRegistry();
        $this->invalidator     = new TemplateInvalidator($this->cacheManager, $this->discovery, $this->extension);
        // Set persistence path for discovery stats
        $persistDir = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'twig';
        if (! is_dir($persistDir)) {
            @mkdir($persistDir, 0775, true);
        }
        $this->discovery->setPersistPath($persistDir . DIRECTORY_SEPARATOR . 'discovery-stats.json');
        $this->initialize($config);
        // After initialize decide discovery persistence medium (remote cache vs filesystem)
        if ($this->usingCacheService) {
            try {
                $ciCache = Services::cache();
                if ($ciCache && method_exists($this->discovery, 'useCiCache')) {
                    $this->discovery->useCiCache($ciCache, $this->cachePrefix ?? 'twig_', $this->cacheTtl ?? 0);
                }
            } catch (Throwable) { // ignore
            }
        }
        $this->discovery->loadPersisted();
    }

    public function initialize(?TwigConfig $config = null): Twig
    {
        if (empty($config)) {
            /** @var TwigConfig $config */
            $config = config('Twig');
        }

        $this->debug = ENVIRONMENT !== 'production';

        $this->extensions = $this->unique_matrix($config->extensions);

        // Logging now relies solely on the framework helper log_message(); no internal logger stored.

        if (isset($config->extension) && $config->extension !== '') {
            $this->extension    = $config->extension;
            $this->cacheManager = new TemplateCacheManager($this->extension);
        }

        if (isset($config->functions_asis)) {
            $this->functions_asis = $this->unique_matrix(array_merge($this->functions_asis, $config->functions_asis));
        }

        if (isset($config->functions_safe)) {
            $this->functions_safe = $this->unique_matrix(array_merge($this->functions_safe, $config->functions_safe));
        }

        if (isset($config->paths)) {
            $this->paths = $this->unique_matrix(array_merge($this->paths, $config->paths));
        }

        $this->filters = $this->unique_matrix(array_merge($this->filters, $config->filters));

        // default Twig config (allow overriding cache path via config property)
        $cachePath = $config->cachePath ?? (WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'twig');
        // Cache may be replaced later with adapter if a non-file cache service is active
        $this->config = [
            'cache'            => $cachePath,
            'debug'            => $this->debug,
            'autoescape'       => 'html',
            'strict_variables' => $config->strictVariables ?? false,
        ];

        // Backend auto-detection: always try service('cache'). Non-file handler => remote cache service.
        try {
            $svc = Services::cache();
            if ($svc) {
                $handlerClass            = $svc::class;
                $isFile                  = str_contains(strtolower($handlerClass), strtolower('File')); // heuristic for FileHandler
                $this->usingCacheService = ! $isFile;
            } else {
                $this->usingCacheService = false;
            }
        } catch (Throwable) {
            $this->usingCacheService = false;
        }
        $this->cachePrefix = $this->deriveCachePrefix();
        $this->cacheTtl    = $config->cacheTtl ?? 0;

        if (isset($config->saveData)) {
            $this->saveData = $config->saveData;
        }

        // Configure discovery performance options (introduced advanced flags)
        if (method_exists($this->discovery, 'configure')) {
            // Recompute capabilities; snapshot decision derived from lean/override only now.
            $this->computeCapabilities($config);
            $persistSnapshot = $this->capabilities['discoverySnapshot'];
            // Auto strategy: preload & APCu usage enabled when snapshot persistence active (cheap heuristics)
            $enablePreload = $persistSnapshot; // simplifies config surface
            $useAPCu       = $persistSnapshot && (function_exists('apcu_enabled') && apcu_enabled());
            $mtimeDepth    = $persistSnapshot ? 0 : 0; // depth currently fixed; retained parameter for API stability
            $this->discovery->configure(
                $persistSnapshot,
                $enablePreload,
                $useAPCu,
                $mtimeDepth,
            );
        }

        return $this;
    }

    /**
     * Derive final cache prefix.
     * New simplified rule (requested):
     *  - If global Config\Cache::$prefix ends with '_' (after trimming whitespace) => return '_twig_'
     *  - Otherwise => return 'twig_'
     * Rationale: avoid incorporating variable project prefixes to prevent accidental duplication
     * and keep key size minimal while allowing a separator when a global underscore already exists.
     */
    private function deriveCachePrefix(): string
    {
        $globalPrefix = '';

        try {
            $cacheCfg = config('Cache');
            if ($cacheCfg && property_exists($cacheCfg, 'prefix') && is_string($cacheCfg->prefix)) {
                $globalPrefix = trim($cacheCfg->prefix);
            }
        } catch (Throwable) { // ignore
        }
        if ($globalPrefix !== '' && str_ends_with($globalPrefix, '_')) {
            return '_twig_';
        }

        return 'twig_';
    }

    /**
     * Resolve final capability matrix from leanMode + nullable overrides.
     * Delegates to {@see \Daycry\Twig\Config\CapabilitiesProfile} so the
     * resolution rules can be tested independently of the Twig facade.
     */
    private function computeCapabilities(TwigConfig $config): void
    {
        $this->capabilities = CapabilitiesProfile::fromConfig($config)->toArray();
    }

    public function resetTwig(): void
    {
        $this->twig = null;
        if (function_exists('log_message')) {
            log_message('debug', 'event=twig.reset');
        }
        // Invalidate discovery cache on reset via service
        $this->discovery->invalidate();
        $this->createTwig();
        $this->environmentResets++;
    }

    /**
     * @param string $uri
     * @param string $title
     * @param array  $attributes [changed] only array is acceptable
     */
    public function safe_anchor($uri = '', $title = '', $attributes = []): string
    {
        $uri   = esc($uri, 'url');
        $title = esc($title);

        $new_attr = [];

        foreach ($attributes as $key => $val) {
            $new_attr[esc($key)] = $val;
        }

        return anchor($uri, $title, $new_attr);
    }

    /**
     * @codeCoverageIgnore
     */
    public function validation_list_errors(): string
    {
        return Services::validation()->listErrors();
    }

    public function getTwig(): Environment
    {
        $this->createTwig();

        return $this->twig;
    }

    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Renders Twig Template and Set Output
     *
     * @param string $view   Template filename without `.twig`
     * @param array  $params Array of parameters to pass to the template
     */
    public function display(string $view, array $params = [])
    {
        echo $this->render($view, $params);
    }

    /**
     * Renders Twig Template and Returns as String
     *
     * @param string $view   Template filename without `.twig`
     * @param array  $params Array of parameters to pass to the template
     */
    public function render(string $view, array $params = []): string
    {
        $start = microtime(true);
        $data  = esc($params, 'raw');
        $this->tempData ??= $this->data;
        $this->tempData = array_merge($this->tempData, $data);

        // Make our view data available to the view.
        $this->prepareTemplateData($this->saveData);

        $this->createTwig();
        // We call addFunctions() here, because we must call addFunctions()
        // after loading CodeIgniter functions in a controller.
        $this->addFunctions();

        $view .= $this->extension;

        $output = $this->twig->render($view, $params);
        // Update render instrumentation
        $end     = microtime(true);
        $elapsed = $end - $start;
        $this->renderCount++;
        $this->totalRenderTime += $elapsed;
        if ($this->capabilities['extendedDiagnostics']) {
            $this->renderProfiler->record($view, $elapsed);
        }

        // Check if DebugToolbar is enabled.
        $filters              = service('filters');
        $requiredAfterFilters = $filters->getRequiredFilters('after')[0];

        if (in_array('toolbar', $requiredAfterFilters, true)) {
            $debugBarEnabled = true;
        } else {
            $afterFilters    = $filters->getFiltersClass()['after'];
            $debugBarEnabled = in_array(DebugToolbar::class, $afterFilters, true);
        }

        if ($this->debug && $debugBarEnabled) {
            $this->logPerformance($start, microtime(true), $view);

            $toolbarCollectors = config(Toolbar::class)->collectors;

            if (in_array(CollectorsTwig::class, $toolbarCollectors, true)) {
                $output = '<!-- DEBUG-VIEW START ' . $view . ' -->' . PHP_EOL
                    . $output . PHP_EOL
                    . '<!-- DEBUG-VIEW ENDED ' . $view . ' -->' . PHP_EOL;
            }
        }
        $this->tempData = null;

        return $output;
    }

    public function createTemplate(string $template, array $params = [], bool $display = false)
    {
        $this->createTwig();
        $this->addFunctions();
        $template = $this->twig->createTemplate($template);
        if (! $display) {
            return $template->render($params);
        }
        echo $template->render($params);
    }

    public function getPerformanceData(): array
    {
        return $this->performanceData;
    }

    public function getData(): array
    {
        return $this->tempData ?? $this->data;
    }

    protected function createTwig(): void
    {
        if ($this->twig !== null) {
            return;
        }
        if ($this->loader === null) {
            $this->loader = new FilesystemLoader();
        }
        if ($this->loader instanceof FilesystemLoader) {
            $fsLoader = $this->loader; // local typed alias

            foreach ($this->paths as $path) {
                if (is_array($path)) {
                    $p = is_string($path[0]) && is_dir($path[0]) ? (realpath($path[0]) ?: $path[0]) : $path[0];
                    $fsLoader->addPath($p, $path[1]);
                } else {
                    $p = is_string($path) && is_dir($path) ? (realpath($path) ?: $path) : $path;
                    $fsLoader->addPath($p);
                }
            }
        }
        // Swap cache option with adapter when using non-file cache service.
        if ($this->usingCacheService) {
            try {
                $ciCache = Services::cache();
                if ($ciCache) {
                    $adapter               = new CICacheAdapter($ciCache, $this->cachePrefix ?? 'twig_', $this->cacheTtl ?? 0);
                    $this->config['cache'] = $adapter; // Twig accepts CacheInterface
                }
            } catch (Throwable $e) {
                $this->logSwallowed('cache.adapter.swap_failed', $e);
            }
        }
        $twig = new Environment($this->loader, $this->config);
        if ($this->debug) {
            $twig->addExtension(new DebugExtension());
        }

        foreach ($this->extensions as $extension) {
            $twig->addExtension(new $extension());
        }

        foreach ($this->pendingExtensions as $ext) {
            $twig->addExtension(new $ext());
        }
        $this->twig = $twig;
        if ($this->autoescapeNamespaceMap !== []) {
            $this->applyAutoescapeStrategy();
        }
    }

    protected function setLoader($loader)
    {
        $this->loader = $loader;
    }

    /**
     * Public fluent API to replace the internal Loader.
     * Resets the current Twig Environment so that subsequent renders
     * use the new loader. Existing configuration (filters/functions/extensions)
     * will be re-applied lazily on next render.
     */
    public function withLoader(LoaderInterface $loader): self
    {
        $this->loader          = $loader;
        $this->twig            = null; // force re-create
        $this->functions_added = false; // ensure functions re-added for new environment
        // Invalidate discovery cache because loader changed
        $this->discovery->invalidate();
        if (function_exists('log_message')) {
            log_message('debug', 'event=twig.loader.replaced loader=' . $loader::class);
        }

        return $this;
    }

    /**
     * Registers a Global
     *
     * @param string $name  The global name
     * @param mixed  $value The global value
     */
    public function addGlobal($name, $value): void
    {
        $this->createTwig();
        $this->twig->addGlobal($name, $value);
    }

    protected function addFunctions(): void
    {
        // Runs only once
        if ($this->functions_added) {
            return;
        }
        if (function_exists('log_message')) {
            log_message('debug', 'event=twig.functions.start');
        }

        // Attempt to autoload helpers if configured functions not yet defined.
        $maybeMissing = array_merge($this->functions_asis, $this->functions_safe);
        $needHelpers  = [];

        foreach ($maybeMissing as $fn) {
            if (! function_exists($fn)) {
                $needHelpers[$fn] = true;
            }
        }
        if ($needHelpers !== []) {
            // Common CodeIgniter helpers that define many of these
            $candidateHelpers = ['form', 'security', 'url', 'text'];

            foreach ($candidateHelpers as $h) {
                if (function_exists('helper')) {
                    helper($h);
                }
            }
            // Recheck; custom minifier/lang might come from project-specific helpers
            if (function_exists('helper')) {
                if (isset($needHelpers['minifier'])) {
                    @helper('minifier');
                }
                if (isset($needHelpers['lang'])) {
                    @helper('language');
                }
            }
        }

        // as-is functions (register only those that now exist)
        foreach ($this->functions_asis as $function) {
            if (function_exists($function)) {
                $this->twig->addFunction(new TwigFunction($function, $function));
            }
        }

        // safe functions
        foreach ($this->functions_safe as $function) {
            if (function_exists($function)) {
                $this->twig->addFunction(new TwigFunction($function, $function, ['is_safe' => ['html']]));
            }
        }

        // static filters from config (always safe html here to preserve previous behavior)
        foreach ($this->filters as $name => $filter) {
            $this->twig->addFilter(new TwigFilter($name, $filter, ['is_variadic' => true, 'is_safe' => ['html']]));
        }
        // apply dynamic filters/functions via registry (includes queued & persisted)
        $this->dynamicRegistry->apply($this->twig);

        // customized functions
        if (function_exists('anchor')) {
            $this->twig->addFunction(new TwigFunction('anchor', $this->safe_anchor(...), ['is_safe' => ['html']]));
        }

        $this->twig->addFunction(new TwigFunction('validation_list_errors', $this->validation_list_errors(...), ['is_safe' => ['html']]));
        // dynamic registry apply manages its own internal queues

        $this->functions_added = true;
        if (function_exists('log_message')) {
            log_message('debug', 'event=twig.functions.ready');
        }
    }

    /**
     * Register a Twig function dynamically (available in subsequent renders).
     *
     * @param mixed $options
     */
    public function registerFunction(string $name, callable $callable, $options = []): self
    {
        // Backward compatibility: boolean indicates safe html
        if (is_bool($options)) {
            $options = $options ? ['is_safe' => ['html']] : [];
        }
        if (! is_array($options)) {
            throw new InvalidArgumentException('Function options must be array or bool.');
        }
        $this->dynamicRegistry->registerFunction($name, $callable, $options, $this->twig, $this->functions_added);

        return $this;
    }

    /**
     * Register a Twig filter dynamically.
     *
     * @param mixed $options
     */
    public function registerFilter(string $name, callable $callable, $options = ['is_safe' => ['html']]): self
    {
        if (is_bool($options)) { // backward compatibility bool parameter
            $options = $options ? ['is_safe' => ['html']] : [];
        }
        if (! is_array($options)) {
            throw new InvalidArgumentException('Filter options must be array or bool.');
        }
        $this->dynamicRegistry->registerFilter($name, $callable, $options, $this->twig, $this->functions_added);

        return $this;
    }

    /**
     * Logs performance data for rendering a view.
     */
    protected function logPerformance(float $start, float $end, string $view): void
    {
        $this->performanceData[] = [
            'start' => $start,
            'end'   => $end,
            'view'  => $view,
        ];
    }

    protected function prepareTemplateData(bool $saveData): void
    {
        $this->tempData ??= $this->data;

        if ($saveData) {
            $this->data = $this->tempData;
        }
    }

    private function unique_matrix(array $matrix): array
    {
        // Preserve keys for associative arrays (e.g. filters => callable) and
        // perform value-based deduplication for sequential (indexed) arrays.

        $isAssoc = array_keys($matrix) !== range(0, count($matrix) - 1);

        if ($isAssoc) {
            // Keep first occurrence of each key only.
            $result = [];

            foreach ($matrix as $k => $v) {
                if (! array_key_exists($k, $result)) {
                    $result[$k] = $v;
                }
            }

            return $result;
        }

        // Indexed array branch: linear de-dup preserving order.
        $seen   = [];
        $result = [];

        foreach ($matrix as $item) {
            $key = is_array($item)
                ? 'a:' . json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : 's:' . $item;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[]   = $item;
        }

        return $result;
    }

    /**
     * Returns the configured Twig cache directory path (ensures it exists).
     */
    public function getCachePath(): string
    {
        $this->createTwig();
        if ($this->usingCacheService) {
            return ''; // no filesystem path when using remote cache service
        }
        $cache = $this->config['cache'] ?? '';
        if (is_string($cache) && $cache !== '' && ! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }

        return is_string($cache) ? $cache : '';
    }

    /**
     * Clears compiled Twig templates. If $reinitialize is true, resets
     * the Twig Environment so new templates will be recompiled lazily.
     * Returns number of removed files.
     */
    public function clearCache(bool $reinitialize = false): int
    {
        if ($this->usingCacheService) {
            $this->createTwig();
            $removed = 0; // we cannot count precisely without scanning index again; adapter handles clear

            try {
                $cacheObj = $this->config['cache'];
                if ($cacheObj instanceof CICacheAdapter) {
                    $cacheObj->clear();
                }

                // Also remove persisted artifact keys stored in CI cache
                try {
                    $ciCache = Services::cache();
                    if ($ciCache) {
                        $prefix = $this->cachePrefix ?? 'twig_';
                        $keys   = [
                            $prefix . 'disc.stats',
                            $prefix . 'disc.list',
                            $prefix . 'warmup.summary',
                            $prefix . 'compile.index',
                            $prefix . 'invalidations',
                        ];

                        foreach ($keys as $k) {
                            $ciCache->delete($k);
                        }
                    }
                } catch (Throwable $e) {
                    $this->logSwallowed('cache.clear.ci.delete', $e);
                }
                // Reset in-memory tracking
                $this->lastWarmup            = null;
                $this->lastInvalidation      = null;
                $this->cumulativeInvalidated = 0;
                $this->discovery->invalidate(); // reset discovery cache stats (will persist fresh on next use)
            } catch (Throwable $e) {
                $this->logSwallowed('cache.clear.ci.outer', $e);
            }
            if ($reinitialize) {
                $this->resetTwig();
            }
            if (function_exists('log_message')) {
                log_message('info', 'event=twig.cache.cleared backend=ci');
            }

            return $removed;
        }
        $cachePath = $this->getCachePath();
        if ($cachePath === '' || ! is_dir($cachePath)) {
            return 0;
        }
        $removed  = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cachePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if ($file->isFile()) {
                if (@unlink($path)) {
                    $removed++;
                }
            }
        }
        if ($reinitialize) {
            $this->resetTwig();
        }
        if (function_exists('log_message')) {
            log_message('info', 'event=twig.cache.cleared removed=' . $removed . ' reinit=' . (int) $reinitialize);
        }

        return $removed;
    }

    /**
     * Attempts to invalidate a single template's compiled cache file(s).
     * Best-effort: Twig's default compiled filenames are hashes of the logical name,
     * so we search for files containing the md5 of the logical template (with extension).
     * Returns number of files removed.
     */
    public function invalidateTemplate(string $logicalName, bool $reinitialize = false): int
    {
        $logicalName = TemplateNameValidator::assertValid($logicalName);
        $this->createTwig();
        $cacheDir = $this->getCachePath();
        $removed  = $this->invalidator->invalidateOne($logicalName, $cacheDir, $reinitialize, fn () => $this->resetTwig(), static function ($level, $msg) { if (function_exists('log_message')) { log_message($level, $msg); } });
        if ($removed > 0) {
            $this->saveCompileIndex();
            $this->cumulativeInvalidated += $removed;
            $this->lastInvalidation = ['type' => 'single', 'removed' => $removed, 'reinit' => $reinitialize, 'timestamp' => microtime(true)];
            $this->saveInvalidationsState();
        }

        return $removed;
    }

    /**
     * Precompiles (warms) a list of logical template names (without extension).
     * Skips templates whose compiled cache already exists unless $force = true.
     * Returns array with keys: compiled (int), skipped (int), errors (int).
     *
     * @param list<string> $templates
     */
    public function warmup(array $templates, bool $force = false): array
    {
        $this->createTwig();
        // Ensure functions/filters/extensions are applied so templates referencing them compile.
        $this->addFunctions();
        $cacheDir     = $this->getCachePath();
        $compiled     = $skipped = $errors = 0;
        $errorDetails = [];
        $this->loadCompileIndex();
        // Pre-build a single hash->present index so the per-template lookup is O(1)
        // instead of O(files) for each call to templateIsCompiled().
        $compiledHashIndex = $force ? [] : $this->buildCompiledHashIndex($cacheDir);

        foreach ($templates as $logical) {
            $logical = trim($logical);
            if ($logical === '') {
                continue;
            }
            $name = $logical . $this->extension;
            $hash = md5($name);
            // Use cache manager state or pre-built hash index (avoids repeated recursive scans).
            $already = $this->cacheManager->isCompiled($logical) || isset($compiledHashIndex[$hash]);
            if ($already && ! $force) {
                $skipped++;

                continue;
            }

            try {
                // loadTemplate triggers compilation; discard returned template
                $this->twig->load($name);
                $this->cacheManager->markCompiled($logical);
                $compiled++;
                if (function_exists('log_message')) {
                    log_message('info', 'event=twig.warmup.compiled template=' . $logical);
                }
            } catch (Throwable $e) {
                $errors++;
                $msg = str_replace(["\n", "\r"], ' ', $e->getMessage());
                if (function_exists('log_message')) {
                    log_message('error', 'event=twig.warmup.error template=' . $logical . ' message=' . $msg);
                }
                // Collect details when verbose diagnostic env flag set
                if (getenv('TWIG_WARMUP_VERBOSE')) {
                    $errorDetails[] = ['template' => $logical, 'error' => $msg];
                }
            }
        }
        if ($compiled > 0) {
            $this->saveCompileIndex();
        }
        $summary = ['compiled' => $compiled, 'skipped' => $skipped, 'errors' => $errors];
        if ($errorDetails !== []) {
            $summary['error_details'] = $errorDetails;
        }
        $this->lastWarmup = ['summary' => $summary, 'all' => false, 'timestamp' => microtime(true)];
        $this->saveWarmupSummary();
        // Dispatch post-warmup event (subset) for external cache invalidation hooks
        if (function_exists('event')) {
            try {
                @event('twig:warmup:after', $summary + ['mode' => 'subset']);
            } catch (Throwable $e) {
                $this->logSwallowed('warmup.event.subset', $e);
            }
        }

        return $summary;
    }

    /**
     * Attempts to warm all templates discovered in configured loader paths.
     * Only works for FilesystemLoader. Non-recursive by namespace; recursively scans directories.
     */
    public function warmupAll(bool $force = false): array
    {
        if (! $this->loader instanceof FilesystemLoader) {
            return ['compiled' => 0, 'skipped' => 0, 'errors' => 0];
        }
        // Reuse discovery (benefits from in-process cache)
        $templates = $this->listAllLogicalTemplates();
        $result    = $this->warmup($templates, $force);
        // Override lastWarmup flag to indicate full warmup
        if ($this->lastWarmup !== null) {
            $this->lastWarmup['all'] = true;
            $this->saveWarmupSummary();
        }
        // Explicit full warmup event (include list size only to avoid large payload)
        if (function_exists('event')) {
            $payload = $result + ['mode' => 'all', 'template_count' => count($templates)];

            try {
                @event('twig:warmup:after', $payload);
            } catch (Throwable $e) {
                $this->logSwallowed('warmup.event.all', $e);
            }
        }

        return $result;
    }

    /**
     * Scan the compiled-template directory once and return a `hash => true` map
     * for fast batch lookup. Twig stores compiled classes as `<2 hex>/<32 hex>.php`
     * (or as `<32 hex>.php` directly), so the hex prefix of the filename is the
     * full md5 of `<logical>.<extension>`.
     *
     * @return array<string,bool>
     */
    private function buildCompiledHashIndex(string $cacheDir): array
    {
        if ($cacheDir === '' || ! is_dir($cacheDir)) {
            return [];
        }
        $index = [];

        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS));

            /** @var SplFileInfo $fi */
            foreach ($it as $fi) {
                if (! $fi->isFile()) {
                    continue;
                }
                $name = $fi->getFilename();
                // Twig compiled filenames begin with the md5 of the logical name+extension.
                if (preg_match('/^([a-f0-9]{32})/', $name, $m) === 1) {
                    $index[$m[1]] = true;
                }
            }
        } catch (Throwable $e) {
            $this->logSwallowed('warmup.scan_failed', $e);
        }

        return $index;
    }

    /**
     * Registers a Twig Extension dynamically. If the Environment already exists
     * the extension is added immediately, otherwise it is queued.
     *
     * @param class-string<ExtensionInterface> $extensionFqcn
     */
    public function registerExtension(string $extensionFqcn): self
    {
        if (! in_array($extensionFqcn, $this->pendingExtensions, true) && ! in_array($extensionFqcn, $this->extensions, true)) {
            if ($this->twig !== null) {
                try {
                    $this->twig->addExtension(new $extensionFqcn());
                    if (function_exists('log_message')) {
                        log_message('info', 'event=twig.extension.registered extension=' . $extensionFqcn);
                    }
                } catch (LogicException) {
                    // Extensions already initialized: queue and force recreation
                    $this->pendingExtensions[] = $extensionFqcn;
                    $this->twig                = null;
                    $this->functions_added     = false;
                    if (function_exists('log_message')) {
                        log_message('info', 'event=twig.extension.queued_recreate extension=' . $extensionFqcn);
                    }
                }
            } else {
                $this->pendingExtensions[] = $extensionFqcn;
                if (function_exists('log_message')) {
                    log_message('info', 'event=twig.extension.queued extension=' . $extensionFqcn);
                }
            }
        }

        return $this;
    }

    /**
     * Unregister a previously registered Twig Extension (dynamic only, not those from config).
     */
    public function unregisterExtension(string $extensionFqcn): bool
    {
        $removed = false;
        // remove from pending first
        $idx = array_search($extensionFqcn, $this->pendingExtensions, true);
        if ($idx !== false) {
            array_splice($this->pendingExtensions, $idx, 1);
            $removed = true;
        }
        // extensions loaded at construction are in $this->extensions (config) – do not remove those
        if ($removed && $this->twig !== null) {
            // rebuild environment without extension
            $this->twig            = null;
            $this->functions_added = false;
        } elseif (! $removed && $this->twig !== null) {
            // If the extension was added dynamically after creation we cannot introspect easily; force rebuild and skip re-adding
            if (in_array($extensionFqcn, $this->extensions, true)) {
                // cannot remove config extension
                return false;
            }
            // There's a chance it's a dynamically added one not in pending (already applied). We rebuild and mark removed by preventing requeue.
            $removed               = true; // treat as removed for caller
            $this->twig            = null;
            $this->functions_added = false;
        }
        if ($removed && function_exists('log_message')) {
            log_message('info', 'event=twig.extension.unregistered extension=' . $extensionFqcn);
        }

        return $removed;
    }

    /**
     * Unregister a dynamically registered function.
     */
    public function unregisterFunction(string $name): bool
    {
        $removed = $this->dynamicRegistry->unregisterFunction($name);
        if ($removed) {
            $this->twig            = null;
            $this->functions_added = false;
        }

        return $removed;
    }

    /**
     * Unregister a dynamically registered filter.
     */
    public function unregisterFilter(string $name): bool
    {
        $removed = $this->dynamicRegistry->unregisterFilter($name);
        if ($removed) {
            $this->twig            = null;
            $this->functions_added = false;
        }

        return $removed;
    }

    /**
     * Disable template cache (optionally deleting existing compiled templates).
     */
    public function disableCache(bool $deleteExisting = false): void
    {
        if ($deleteExisting) {
            $this->clearCache(false);
        }
        $this->config['cache'] = false;
        $this->twig            = null;
        $this->functions_added = false;
        if (function_exists('log_message')) {
            log_message('info', 'event=twig.cache.disabled');
        }
    }

    /**
     * Enable template cache (optionally with custom path).
     */
    public function enableCache(?string $path = null): void
    {
        if ($path !== null) {
            $this->config['cache'] = $path;
        } elseif (! isset($this->config['cache']) || $this->config['cache'] === false) {
            // default path
            $this->config['cache'] = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'twig';
        }
        $this->getCachePath(); // ensure exists
        $this->twig            = null;
        $this->functions_added = false;
        if (function_exists('log_message')) {
            log_message('info', 'event=twig.cache.enabled path=' . $this->config['cache']);
        }
    }

    public function isCacheEnabled(): bool
    {
        return $this->usingCacheService || ! empty($this->config['cache']);
    }

    /** Set autoescape strategy for a namespace (namespace without leading @). */
    /**
     * Set autoescape strategy for a namespace (pass string strategy like 'html','js','css' or false to disable).
     *
     * @param mixed $strategy string strategy or false
     */
    public function setAutoescapeForNamespace(string $namespace, $strategy): self
    {
        $namespace                                = ltrim($namespace, '@');
        $this->autoescapeNamespaceMap[$namespace] = $strategy;
        if ($this->twig !== null) {
            $this->applyAutoescapeStrategy();
        }
        if (function_exists('log_message')) {
            $strategyStr = is_bool($strategy) ? ($strategy ? 'true' : 'false') : (string) $strategy;
            log_message('info', 'event=twig.autoescape.namespace.set namespace=' . $namespace . ' strategy=' . $strategyStr);
        }

        return $this;
    }

    public function removeAutoescapeForNamespace(string $namespace): bool
    {
        $namespace = ltrim($namespace, '@');
        if (! isset($this->autoescapeNamespaceMap[$namespace])) {
            return false;
        }
        unset($this->autoescapeNamespaceMap[$namespace]);
        if ($this->twig !== null) {
            $this->applyAutoescapeStrategy();
        }
        if (function_exists('log_message')) {
            log_message('info', 'event=twig.autoescape.namespace.removed namespace=' . $namespace);
        }

        return true;
    }

    /**
     * Dynamically add a template path at runtime (optionally with a namespace).
     * If the Twig Environment is already created, the loader is updated immediately
     * and discovery cache invalidated so subsequent warmup/list operations see it.
     *
     * Examples:
     *   $twig->addPath(APPPATH.'Modules/Admin/Cookies/Views', 'corporateCookies');
     *   $twig->addPath(APPPATH.'Another/Views'); // main namespace
     */
    public function addPath(string $path, ?string $namespace = null): self
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Twig::addPath: path must be a non-empty string without null bytes.');
        }
        if ($namespace !== null) {
            $namespace = TemplateNameValidator::assertValidNamespace($namespace);
            $namespace = is_string($namespace) ? ltrim($namespace, '@') : null;
        }
        $entry         = $namespace ? [$path, $namespace] : $path;
        $this->paths[] = $entry;
        // Update loader if already instantiated
        if ($this->loader instanceof FilesystemLoader) {
            if ($namespace) {
                $this->loader->addPath($path, $namespace);
            } else {
                $this->loader->addPath($path);
            }
        }
        // Invalidate discovery so new path is included
        $this->discovery->invalidate();
        if (function_exists('log_message')) {
            log_message('info', 'event=twig.path.added path=' . $path . ' namespace=' . $namespace);
        }

        return $this;
    }

    /**
     * Apply current autoescape strategy map to the EscaperExtension.
     */
    private function applyAutoescapeStrategy(): void
    {
        if ($this->twig === null) {
            return;
        }

        try {
            $ext = $this->twig->getExtension('Twig\\Extension\\EscaperExtension');
            if (method_exists($ext, 'setDefaultStrategy')) {
                $map      = $this->autoescapeNamespaceMap;
                $default  = 'html';
                $callable = static function (string $name) use ($map, $default): string|bool {
                    // Determine namespace from template name (@ns/...) pattern
                    if (isset($name[0]) && $name[0] === '@') {
                        $pos = strpos($name, '/');
                        if ($pos !== false) {
                            $ns = substr($name, 1, $pos - 1);
                            if (array_key_exists($ns, $map)) {
                                return $map[$ns];
                            }
                        }
                    }

                    return $default; // fallback
                };
                $ext->setDefaultStrategy($map === [] ? 'html' : $callable);
            }
        } catch (Throwable) {
            // ignore; escaper extension not available yet
        }
    }

    /**
     * Get path to compile index file.
     */
    public function getCompileIndexPath(): string
    {
        if ($this->usingCacheService) {
            return '';
        }

        return rtrim($this->getCachePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'compile-index.json';
    }

    private function loadCompileIndex(): void
    {
        static $loadedByPath = [];
        if ($this->usingCacheService) {
            $key = ($this->cachePrefix ?? 'twig_') . 'compile.index';
            if (isset($loadedByPath[$key])) {
                return;
            }

            try {
                $cache = Services::cache();
                if ($cache) {
                    $raw = $cache->get($key);
                    if (is_string($raw)) {
                        $data = json_decode($raw, true);
                        if (is_array($data)) {
                            $names = [];

                            foreach ($data as $k => $v) {
                                if (is_string($k) && ($v === true || $v === 1)) {
                                    $names[] = $k;
                                }
                            }
                            if ($names !== []) {
                                $this->cacheManager->seedCompiled($names);
                            }
                        }
                    }
                }
            } catch (Throwable) { // ignore
            }
            $loadedByPath[$key] = true;

            return;
        }
        $path = $this->getCompileIndexPath();
        if (isset($loadedByPath[$path])) {
            return;
        }
        $this->cacheManager->loadIndex($path);
        $loadedByPath[$path] = true;
    }

    /**
     * Heuristic fallback: if the compile index is empty but cache directory contains compiled Twig
     * PHP classes (common after upgrade or manual cache copy), we reconstruct a synthetic index so
     * diagnostics show a realistic compiled_templates count. We cannot map back to logical template
     * names without embedded metadata, so we generate placeholder logical IDs (unknown_N). This only
     * runs once per request and only when count=0.
     */
    private function rebuildCompileIndexIfEmpty(): void
    {
        if ($this->usingCacheService) {
            // CI backend already persisted index as JSON; skip heuristic.
            return;
        }
        $indexPath = $this->getCompileIndexPath();
        if ($indexPath === '') {
            return;
        }
        // If we already have entries, nothing to do.
        if (count($this->cacheManager->getCompiledTemplates()) > 0) {
            return;
        }
        $cacheDir = $this->getCachePath();
        if ($cacheDir === '' || ! is_dir($cacheDir)) {
            return;
        }
        $compiledFiles = [];

        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS));

            /** @var SplFileInfo $fi */
            foreach ($it as $fi) {
                if (! $fi->isFile()) {
                    continue;
                }
                // Quick reject: only PHP files
                if (! str_ends_with($fi->getFilename(), '.php')) {
                    continue;
                }
                // Cheap content scan (first 2KB) for Twig template class marker
                $chunk = @file_get_contents($fi->getPathname(), false, null, 0, 2048);
                if (is_string($chunk) && str_contains($chunk, 'class __TwigTemplate_')) {
                    $compiledFiles[] = $fi->getPathname();
                }
            }
        } catch (Throwable) { // ignore scan errors
        }
        if ($compiledFiles === []) {
            return; // nothing to rebuild
        }
        // Seed synthetic logical names
        $i = 1;

        foreach ($compiledFiles as $_) {
            $this->cacheManager->markCompiled('unknown_' . $i++);
        }
        // Persist synthetic index (best-effort)
        $this->saveCompileIndex();
        // Annotate that we reconstructed so UI/diagnostics can clarify
        $this->reconstructedIndex = true; // dynamic property; minimal impact
    }

    private function saveCompileIndex(): void
    {
        if ($this->usingCacheService) {
            try {
                $cache = Services::cache();
                if ($cache) {
                    $key = ($this->cachePrefix ?? 'twig_') . 'compile.index';
                    $cache->save($key, json_encode($this->cacheManager->getCompiledTemplates(), JSON_UNESCAPED_SLASHES), $this->cacheTtl ?? 0);
                }
            } catch (Throwable) { // ignore
            }

            return;
        }
        $path = $this->getCompileIndexPath();
        if ($path === '' || ! is_dir(dirname($path))) {
            return;
        }
        $this->cacheManager->saveIndex($path);
    }

    /**
     * Public listing API.
     * Parameters:
     *  - $withStatus: include compiled status if true
     *  - $namespace: filter by namespace (accepts with or without leading @). If null, returns all.
     *  - $pattern: optional glob-like filter applied to the logical template (after namespace), supports * and ?
     *      Examples: 'admin/*', 'emails/user_*', '*partial', 'dash??/index'
     *      If $namespace provided, pattern does not need to repeat namespace (applies inside namespace root).
     */
    public function listTemplates(bool $withStatus = false, ?string $namespace = null, ?string $pattern = null): array
    {
        $names = $this->listAllLogicalTemplates();
        // Normalize namespace
        $nsFilter = null;
        if ($namespace !== null && $namespace !== '') {
            $nsFilter = ltrim($namespace, '@');
        }
        $filtered = [];
        if ($nsFilter === null && $pattern === null) {
            $filtered = $names; // fast path no filtering
        } else {
            // Pre-build regex if pattern has wildcards
            $regex   = null;
            $hasWild = false;
            if ($pattern !== null && $pattern !== '') {
                $hasWild = strpbrk($pattern, '*?') !== false;
                if ($hasWild) {
                    // escape regex delimiters then replace wildcards
                    $rx    = preg_quote($pattern, '/');
                    $rx    = str_replace(['\\*', '\\?'], ['.*', '.?'], $rx);
                    $regex = '/^' . $rx . '$/i';
                }
            }

            foreach ($names as $logical) {
                $logicalNs        = null;
                $logicalRemainder = $logical;
                if (isset($logical[0]) && $logical[0] === '@') {
                    $pos = strpos($logical, '/');
                    if ($pos !== false) {
                        $logicalNs        = substr($logical, 1, $pos - 1);
                        $logicalRemainder = substr($logical, $pos + 1);
                    } else {
                        $logicalNs        = substr($logical, 1); // template directly under namespace
                        $logicalRemainder = '';
                    }
                }
                if ($nsFilter !== null) {
                    if ($logicalNs !== $nsFilter) {
                        continue;
                    }
                }
                // Determine target name to match pattern against
                $candidate = $nsFilter !== null ? $logicalRemainder : $logical;
                if ($pattern === null || $pattern === '') {
                    $filtered[] = $logical;

                    continue;
                }
                if (! $hasWild) {
                    // simple case-insensitive prefix match if pattern ends with * or exact match otherwise
                    if ($pattern[strlen($pattern) - 1] === '*') {
                        $prefix = substr($pattern, 0, -1);
                        if (str_starts_with(strtolower((string) $candidate), strtolower($prefix))) {
                            $filtered[] = $logical;
                        }
                    } else {
                        if (strcasecmp((string) $candidate, $pattern) === 0) {
                            $filtered[] = $logical;
                        }
                    }

                    continue;
                }
                if ($regex && preg_match($regex, (string) $candidate) === 1) {
                    $filtered[] = $logical;
                }
            }
        }
        $this->loadCompileIndex();
        if (! $withStatus) {
            return $filtered;
        }
        $out = [];

        foreach ($filtered as $n) {
            $out[] = ['name' => $n, 'compiled' => $this->cacheManager->isCompiled($n)];
        }

        return $out;
    }

    /**
     * Invalidate multiple logical templates at once.
     *
     * @param list<string> $logicalNames (without extension)
     */
    public function invalidateTemplates(array $logicalNames, bool $reinitialize = false): array
    {
        $logicalNames = TemplateNameValidator::filterValid($logicalNames, static function (string $raw, string $err): void {
            if (function_exists('log_message')) {
                log_message('warning', 'event=twig.invalidate.skipped raw=' . $raw . ' reason=' . $err);
            }
        });
        $this->createTwig();
        $cacheDir = $this->getCachePath();
        $result   = $this->invalidator->invalidateMany($logicalNames, $cacheDir, $reinitialize, fn () => $this->resetTwig(), static function ($level, $msg) {
            if (function_exists('log_message')) {
                log_message($level, $msg);
            }
        });
        if ($result['removed'] > 0) {
            $this->saveCompileIndex();
            $this->cumulativeInvalidated += $result['removed'];
            $this->lastInvalidation = ['type' => 'batch', 'removed' => $result['removed'], 'reinit' => $reinitialize, 'timestamp' => microtime(true)];
            $this->saveInvalidationsState();
        }

        return $result;
    }

    /**
     * Invalidate all templates in a given namespace (e.g. "@admin") or root if null.
     * Namespace should include leading '@'.
     */
    public function invalidateNamespace(?string $namespace, bool $reinitialize = false): array
    {
        $namespace = TemplateNameValidator::assertValidNamespace($namespace);
        $this->createTwig();
        if (! $this->loader instanceof FilesystemLoader) {
            return ['removed' => 0, 'templates' => [], 'reinit' => false];
        }
        $cacheDir = $this->getCachePath();
        $result   = $this->invalidator->invalidateNamespace($namespace, $cacheDir, $reinitialize, $this->loader, fn () => $this->resetTwig(), static function ($level, $msg) {
            if (function_exists('log_message')) {
                log_message($level, $msg);
            }
        });
        if ($result['removed'] > 0) {
            $this->saveCompileIndex();
            $this->cumulativeInvalidated += $result['removed'];
            $this->lastInvalidation = ['type' => 'namespace', 'removed' => $result['removed'], 'reinit' => $reinitialize, 'timestamp' => microtime(true)];
            $this->saveInvalidationsState();
        }

        return $result;
    }

    /**
     * Aggregate diagnostics for the debug toolbar and external observability.
     * Sections are conditionally included based on the active capability
     * profile (Lean vs Full + nullable overrides). See
     * `docs/DIAGNOSTICS_REFERENCE.md` for the full schema.
     *
     * @return array<string,mixed>
     */
    public function getDiagnostics(): array
    {
        // Ensure compile index loaded so compiled templates count is accurate when called early in request.
        if (method_exists($this, 'loadCompileIndex')) {
            try {
                $this->loadCompileIndex();
                $this->rebuildCompileIndexIfEmpty();
            } catch (Throwable $e) {
                $this->logSwallowed('diagnostics.compile_index.load', $e);
            }
        }
        // Load persisted warmup summary if not already present (e.g. CLI warmup in previous request)
        if ($this->lastWarmup === null) {
            $this->loadWarmupSummary();
        }
        // Load persisted invalidations state if not present
        if ($this->lastInvalidation === null && $this->cumulativeInvalidated === 0) {
            $this->loadInvalidationsState();
        }
        // reload persisted discovery stats if available (non-destructive)
        $this->discovery->loadPersisted();
        $discoveryStats = $this->discovery->getStats();
        // We no longer force an eager discovery scan here. First-request toolbar will
        // show persistedCount (if available) or defer full list enumeration until
        // explicitly requested (e.g., warmup or manual listing) to minimize latency.
        $fnCounts     = $this->dynamicRegistry->getFunctionCounts();
        $filterCounts = $this->dynamicRegistry->getFilterCounts();
        // Static (configured) functions & filters: show how many are configured regardless of dynamic registry usage.
        // We purposely do NOT call addFunctions() here to avoid premature helper loading; these are configured counts.
        $staticFnConfigured     = count($this->functions_asis) + count($this->functions_safe);
        $staticFilterConfigured = count($this->filters);
        // Collect name lists (static & dynamic) for richer diagnostics (not persisted). Truncate if large.
        $dynamicFunctionNames = method_exists($this->dynamicRegistry, 'listFunctionNames') ? $this->dynamicRegistry->listFunctionNames() : [];
        $dynamicFilterNames   = method_exists($this->dynamicRegistry, 'listFilterNames') ? $this->dynamicRegistry->listFilterNames() : [];
        $truncateList         = static function (array $items, int $limit = 50): array {
            if (count($items) <= $limit) {
                return $items;
            }

            return array_slice($items, 0, $limit);
        };
        $staticFunctionNames    = array_merge($this->functions_asis, $this->functions_safe);
        $staticFilterNames      = array_keys($this->filters);
        $compiledTemplatesCount = method_exists($this->cacheManager, 'getCompiledTemplates') ? count($this->cacheManager->getCompiledTemplates()) : null;
        $avgRender              = $this->renderCount ? $this->totalRenderTime / $this->renderCount : 0.0;
        $lastView               = null;
        if (! empty($this->performanceData)) {
            $lastRow  = $this->performanceData[count($this->performanceData) - 1];
            $lastView = $lastRow['view'] ?? null;
        }

        // Base always-present sections
        $serviceClass = null;
        if ($this->usingCacheService) {
            try {
                $c            = Services::cache();
                $serviceClass = $c ? $c::class : null;
            } catch (Throwable) {
                $serviceClass = null;
            }
        }
        $diag = [
            'renders'            => $this->renderCount,
            'last_render_view'   => $lastView,
            'environment_resets' => $this->environmentResets,
            'cache'              => [
                'enabled'             => $this->isCacheEnabled(),
                'path'                => $this->isCacheEnabled() ? $this->getCachePath() : null,
                'mode'                => $this->usingCacheService ? 'service' : 'filesystem',
                'service_class'       => $serviceClass,
                'prefix'              => $this->cachePrefix,
                'ttl'                 => $this->cacheTtl,
                'compiled_templates'  => $compiledTemplatesCount,
                'reconstructed_index' => property_exists($this, 'reconstructedIndex'),
            ],
            'performance' => [
                'total_render_time_ms' => round($this->totalRenderTime * 1000, 2),
                'avg_render_time_ms'   => round($avgRender * 1000, 2),
            ],
            'capabilities' => $this->capabilities,
        ];

        // Only include extended sections if capability enabled to keep lean truly minimal.
        if ($this->capabilities['dynamicMetrics']) {
            $diag['dynamic_functions'] = $fnCounts;
            $diag['dynamic_filters']   = $filterCounts;
        }

        // Static counts useful for debugging, keep only if extendedDiagnostics enabled to reduce size.
        if ($this->capabilities['extendedDiagnostics']) {
            $diag['static_functions'] = ['configured' => $staticFnConfigured];
            $diag['static_filters']   = ['configured' => $staticFilterConfigured];
            $diag['extensions']       = [
                'configured' => count($this->extensions),
                'pending'    => count($this->pendingExtensions),
            ];
            $diag['performance']['per_template']  = $this->renderProfiler->snapshot();
            $diag['performance']['top_templates'] = $this->renderProfiler->topByTotal(10);
        }

        if ($this->capabilities['extendedDiagnostics']) {
            $diag['names'] = [
                'static_functions'  => $truncateList($staticFunctionNames),
                'dynamic_functions' => $truncateList($dynamicFunctionNames),
                'static_filters'    => $truncateList($staticFilterNames),
                'dynamic_filters'   => $truncateList($dynamicFilterNames),
            ];
        }

        if ($this->capabilities['warmupSummary']) {
            $diag['warmup'] = $this->lastWarmup;
        }
        if ($this->capabilities['invalidationHistory']) {
            $diag['invalidations'] = [
                'last'               => $this->lastInvalidation,
                'cumulative_removed' => $this->cumulativeInvalidated,
            ];
        }
        if ($this->capabilities['discoverySnapshot']) {
            $diag['discovery'] = $discoveryStats + [
                'persistence_medium' => method_exists($this->discovery, 'getPersistenceMedium') ? $this->discovery->getPersistenceMedium() : 'file',
            ];
        }
        // Persistence mediums only relevant if any persistence features are on; always show compile_index medium for transparency.
        $diag['persistence'] = [
            'compile_index' => ['medium' => $this->usingCacheService ? 'ci' : 'file'],
        ];
        if ($this->capabilities['discoverySnapshot']) {
            $diag['persistence']['discovery_snapshot'] = ['medium' => method_exists($this->discovery, 'getPersistenceMedium') ? $this->discovery->getPersistenceMedium() : ($this->usingCacheService ? 'ci' : 'file')];
        }
        if ($this->capabilities['warmupSummary']) {
            $diag['persistence']['warmup'] = ['medium' => $this->usingCacheService ? 'ci' : 'file'];
        }
        if ($this->capabilities['invalidationHistory']) {
            $diag['persistence']['invalidations'] = ['medium' => $this->usingCacheService ? 'ci' : 'file'];
        }

        return $diag;
    }

    /**
     * Path to persisted warmup summary JSON file.
     */
    private function getWarmupSummaryPath(): string
    {
        return rtrim($this->getCachePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'warmup-summary.json';
    }

    /**
     * Path to invalidations state file (file backend).
     */
    private function getInvalidationsStatePath(): string
    {
        return rtrim($this->getCachePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'invalidations.json';
    }

    /**
     * Persist invalidations state (last + cumulative)
     */
    private function saveInvalidationsState(): void
    {
        if (! $this->capabilities['invalidationHistory']) {
            return;
        }
        $payload = [
            'last'       => $this->lastInvalidation,
            'cumulative' => $this->cumulativeInvalidated,
            'version'    => 1,
        ];
        if ($this->usingCacheService) {
            try {
                $cache = Services::cache();
                if ($cache) {
                    $cache->save(($this->cachePrefix ?? 'twig_') . 'invalidations', json_encode($payload, JSON_UNESCAPED_SLASHES), $this->cacheTtl ?? 0);
                }
            } catch (Throwable $e) {
                $this->logSwallowed('invalidations.save.ci', $e);
            }

            return;
        }
        $path = $this->getInvalidationsStatePath();
        if ($path === '' || ! is_dir(dirname($path))) {
            return;
        }

        try {
            @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            $this->logSwallowed('invalidations.save.file', $e);
        }
    }

    /**
     * Load invalidations state if available.
     */
    private function loadInvalidationsState(): void
    {
        if (! $this->capabilities['invalidationHistory']) {
            return;
        }
        if ($this->usingCacheService) {
            try {
                $cache = Services::cache();
                if ($cache) {
                    $raw  = $cache->get(($this->cachePrefix ?? 'twig_') . 'invalidations');
                    $data = PersistenceDecoder::decode(is_string($raw) ? $raw : null);
                    if ($data !== null) {
                        if (isset($data['cumulative']) && (is_int($data['cumulative']) || is_numeric($data['cumulative']))) {
                            $this->cumulativeInvalidated = (int) $data['cumulative'];
                        }
                        if (isset($data['last']) && is_array($data['last'])) {
                            $this->lastInvalidation = $data['last'];
                        }
                    }
                }
            } catch (Throwable $e) {
                $this->logSwallowed('invalidations.load.ci', $e);
            }

            return;
        }
        $path = $this->getInvalidationsStatePath();
        if (! is_file($path)) {
            return;
        }

        try {
            $raw  = @file_get_contents($path);
            $data = PersistenceDecoder::decode($raw === false ? null : $raw);
            if ($data === null) {
                return;
            }
            if (isset($data['cumulative']) && (is_int($data['cumulative']) || is_numeric($data['cumulative']))) {
                $this->cumulativeInvalidated = (int) $data['cumulative'];
            }
            if (isset($data['last']) && is_array($data['last'])) {
                $this->lastInvalidation = $data['last'];
            }
        } catch (Throwable $e) {
            $this->logSwallowed('invalidations.load.file', $e);
        }
    }

    /**
     * Persist last warmup summary (best-effort).
     */
    private function saveWarmupSummary(): void
    {
        if ($this->lastWarmup === null) {
            return;
        }
        if (! $this->capabilities['warmupSummary']) {
            return;
        }
        $payload = $this->lastWarmup + ['version' => 1];
        if ($this->usingCacheService) {
            try {
                $cache = Services::cache();
                if ($cache) {
                    $cache->save(($this->cachePrefix ?? 'twig_') . 'warmup.summary', json_encode($payload, JSON_UNESCAPED_SLASHES), $this->cacheTtl ?? 0);
                }
            } catch (Throwable $e) {
                $this->logSwallowed('warmup.summary.save.ci', $e);
            }

            return;
        }
        $path = $this->getWarmupSummaryPath();

        try {
            @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            $this->logSwallowed('warmup.summary.save.file', $e);
        }
    }

    /**
     * Load persisted warmup summary if exists.
     */
    private function loadWarmupSummary(): void
    {
        if (! $this->capabilities['warmupSummary']) {
            return;
        }
        if ($this->usingCacheService) {
            try {
                $cache = Services::cache();
                if ($cache) {
                    $json = $cache->get(($this->cachePrefix ?? 'twig_') . 'warmup.summary');
                    $data = PersistenceDecoder::decode(is_string($json) ? $json : null);
                    if ($data !== null && isset($data['summary']) && is_array($data['summary'])) {
                        $this->lastWarmup = [
                            'summary'   => $data['summary'],
                            'all'       => (bool) ($data['all'] ?? false),
                            'timestamp' => isset($data['timestamp']) ? (float) $data['timestamp'] : microtime(true),
                        ];
                    }
                }
            } catch (Throwable $e) {
                $this->logSwallowed('warmup.summary.load.ci', $e);
            }

            return;
        }
        $path = $this->getWarmupSummaryPath();
        if (! is_file($path)) {
            return;
        }

        try {
            $json = @file_get_contents($path);
            $data = PersistenceDecoder::decode($json === false ? null : $json);
            if ($data === null || ! isset($data['summary']) || ! is_array($data['summary'])) {
                return;
            }
            $this->lastWarmup = [
                'summary'   => $data['summary'],
                'all'       => (bool) ($data['all'] ?? false),
                'timestamp' => isset($data['timestamp']) ? (float) $data['timestamp'] : microtime(true),
            ];
        } catch (Throwable $e) {
            $this->logSwallowed('warmup.summary.load.file', $e);
        }
    }

    private function listAllLogicalTemplates(): array
    {
        $this->createTwig();
        if (! $this->loader instanceof FilesystemLoader) {
            return [];
        }

        return $this->discovery->listAll($this->loader, $this->extension);
    }

    // compiledHash moved to TemplateInvalidator (centralized)

    /**
     * Centralized logger for swallowed exceptions: keeps catch(Throwable){} blocks
     * from hiding real production failures while preserving best-effort semantics.
     */
    private function logSwallowed(string $eventSuffix, Throwable $e): void
    {
        $this->log->debug('event=twig.' . $eventSuffix . ' msg=' . $e->getMessage());
    }

    /**
     * Inject (or replace) a PSR-3 logger. When unset, the library falls back to
     * CodeIgniter's `log_message()` helper.
     */
    public function setLogger(?LoggerInterface $logger): self
    {
        $this->log->setLogger($logger);

        return $this;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->log->getLogger();
    }

    // End of class
}
