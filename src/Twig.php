<?php

namespace Daycry\Twig;

use Daycry\Twig\Cache\FilesystemCache;
use Daycry\Twig\Cache\TemplateCacheManager;
use Daycry\Twig\Config\CapabilitiesProfile;
use Daycry\Twig\Contracts\CacheStoreInterface;
use Daycry\Twig\Contracts\ClearableCacheInterface;
use Daycry\Twig\Contracts\RefreshableCacheInterface;
use Daycry\Twig\Discovery\TemplateDiscovery;
use Daycry\Twig\Invalidation\TemplateInvalidator;
use Daycry\Twig\Logging\TwigLogger;
use Daycry\Twig\Persistence\StateRepository;
use Daycry\Twig\Profile\RenderProfiler;
use Daycry\Twig\Registry\DynamicRegistry;
use Daycry\Twig\Runtime\Environment as ManagedEnvironment;
use Daycry\Twig\Support\TemplateNameValidator;
use Daycry\Twig\Warmup\TemplateWarmer;
use FilesystemIterator;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Extension\EscaperExtension;
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
    private array $paths = [];

    /**
     * @var array Functions to add to Twig
     */
    private array $functions_asis = [];

    /**
     * @var array Functions with `is_safe` option
     *
     * @see http://twig.sensiolabs.org/doc/advanced.html#automatic-escaping
     */
    private array $functions_safe = [];

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
    protected bool $functions_added = false;

    private ?Environment $twig = null;

    /**
     * @class \Twig\Loader\FilesystemLoader
     */
    private ?LoaderInterface $loader = null;

    private bool $customLoader          = false;
    private string $environmentRevision = '';
    private array $globals              = [];
    protected array $performanceData    = [];
    protected bool $debug               = false;
    protected bool $saveData            = true;
    protected ?array $tempData          = null;
    protected int $viewsCount           = 0;

    // Diagnostics / instrumentation counters
    private ?string $lastRenderView    = null;
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
    private readonly TemplateDiscovery $discovery;

    private TemplateInvalidator $invalidator;
    private StateRepository $state;
    private TemplateWarmer $warmer;
    private Configuration $options;
    private bool $compileIndexLoaded = false;
    private bool $reconstructedIndex = false;

    // otherwise wrap with CICacheAdapter. Legacy string backend replaced by boolean flag.
    private bool $usingCacheService = false; // true when non-file cache handler in use
    private ?string $cachePrefix    = null;  // resolved during initialize
    private int $cacheTtl           = 0;
    private readonly TwigLogger $log;
    private readonly RenderProfiler $renderProfiler;

    public function __construct(?Configuration $config = null, ?LoggerInterface $logger = null, protected ?CacheStoreInterface $store = null)
    {
        $this->log             = new TwigLogger($logger);
        $this->renderProfiler  = new RenderProfiler();
        $this->discovery       = new TemplateDiscovery($this->log);
        $this->cacheManager    = new TemplateCacheManager();
        $this->dynamicRegistry = new DynamicRegistry($this->log);
        $this->initialize($config);
    }

    public function initialize(?Configuration $config = null): self
    {
        $this->discardEnvironment();
        if (! $this->customLoader) {
            $this->loader = null;
        }
        $this->options           = $config ?? new Configuration();
        $config                  = $this->options;
        $this->debug             = $config->debug;
        $this->extension         = $config->extension;
        $this->paths             = $this->unique_matrix($config->paths);
        $this->functions_asis    = $config->functions_asis;
        $this->functions_safe    = $config->functions_safe;
        $this->filters           = $config->filters;
        $this->extensions        = $this->unique_matrix($config->extensions);
        $this->saveData          = $config->saveData;
        $this->cachePrefix       = $config->cachePrefix;
        $this->cacheTtl          = $config->cacheTtl;
        $this->usingCacheService = $this->store !== null;
        $this->config            = [
            'cache'            => $config->compiledCache ?? $config->cachePath,
            'debug'            => $this->debug,
            'autoescape'       => 'html',
            'strict_variables' => $config->strictVariables,
        ];
        $this->state              = new StateRepository($config->statePath ?? ($config->cachePath ?: null), $this->store, $this->cachePrefix, $this->cacheTtl);
        $this->cacheManager       = new TemplateCacheManager();
        $this->warmer             = new TemplateWarmer($this->cacheManager, $this->state, $this->log);
        $this->compileIndexLoaded = false;
        $this->reconstructedIndex = false;
        $this->invalidator        = new TemplateInvalidator($this->cacheManager, $this->discovery, $this->extension);
        $this->computeCapabilities($config);
        $snapshot = $this->capabilities['discoverySnapshot'];
        $this->discovery->configure($snapshot, $snapshot, $snapshot, 0, $config->discoveryVersion);
        $this->discovery->setRepository($this->state);
        $this->discovery->loadPersisted();

        return $this;
    }

    /**
     * Resolve final capability matrix from leanMode + nullable overrides.
     * Delegates to {@see \Daycry\Twig\Config\CapabilitiesProfile} so the
     * resolution rules can be tested independently of the Twig facade.
     */
    private function computeCapabilities(Configuration $config): void
    {
        $this->capabilities = CapabilitiesProfile::fromConfig($config)->toArray();
    }

    public function resetTwig(): void
    {
        $this->rebuildEnvironment(true);
    }

    private function rebuildEnvironment(bool $isolatedRevision): void
    {
        $this->discardEnvironment($isolatedRevision);
        $this->discovery->invalidate();
        $this->createTwig();
        $this->environmentResets++;
        $this->log->debug('event=twig.reset');
    }

    private function discardEnvironment(bool $isolatedRevision = true): void
    {
        if ($isolatedRevision && $this->twig !== null) {
            $this->environmentRevision = bin2hex(random_bytes(8));
        }
        $this->twig            = null;
        $this->functions_added = false;
    }

    private function prepareEnvironment(): void
    {
        $this->createTwig();
        $cache = $this->twig->getCache(false);
        if ($cache instanceof RefreshableCacheInterface && $cache->refresh()) {
            $this->rebuildEnvironment(false);
        }
        $this->addFunctions();
    }

    protected function afterRender(string $output, string $view, float $start): string
    {
        return $output;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function addFrameworkFunctions(Environment $environment): void
    {
    }

    public function getTwig(): Environment
    {
        $this->prepareEnvironment();

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
     *
     * @phpstan-impure
     */
    public function render(string $view, array $params = []): string
    {
        $start          = microtime(true);
        $this->tempData = array_merge($this->data, $params);
        $context        = $this->tempData;
        $this->prepareTemplateData($this->saveData);

        try {
            $this->prepareEnvironment();
            $name    = $view . $this->extension;
            $output  = $this->twig->render($name, $context);
            $elapsed = microtime(true) - $start;
            $this->renderCount++;
            $this->lastRenderView = $name;
            $this->totalRenderTime += $elapsed;
            if ($this->capabilities['extendedDiagnostics']) {
                $this->renderProfiler->record($name, $elapsed);
            }

            return $this->afterRender($output, $name, $start);
        } finally {
            $this->tempData = null;
        }
    }

    public function createTemplate(string $template, array $params = [], bool $display = false)
    {
        $this->prepareEnvironment();
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
        if ($this->loader === null || ! $this->customLoader) {
            $this->loader = new FilesystemLoader();
        }
        if ($this->loader instanceof FilesystemLoader) {
            $fsLoader = $this->loader; // local typed alias

            foreach ($this->paths as $path) {
                if (is_array($path)) {
                    $p = is_string($path[0]) && is_dir($path[0]) ? (realpath($path[0]) ?: $path[0]) : $path[0];
                    if (! in_array($p, $fsLoader->getPaths($path[1]), true)) {
                        $fsLoader->addPath($p, $path[1]);
                    }
                } else {
                    $p = is_string($path) && is_dir($path) ? (realpath($path) ?: $path) : $path;
                    if (! in_array($p, $fsLoader->getPaths(), true)) {
                        $fsLoader->addPath($p);
                    }
                }
            }
        }
        $environmentOptions = $this->config;
        if (is_string($environmentOptions['cache'])) {
            $environmentOptions['cache'] = new FilesystemCache($environmentOptions['cache']);
        }
        $twig = new ManagedEnvironment($this->loader, $environmentOptions, $this->environmentRevision);
        if ($this->debug) {
            $twig->addExtension(new DebugExtension());
        }

        foreach ($this->extensions as $extension) {
            $twig->addExtension(new $extension());
        }

        foreach ($this->pendingExtensions as $ext) {
            $twig->addExtension(new $ext());
        }

        foreach ($this->globals as $name => $value) {
            $twig->addGlobal($name, $value);
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
        $this->customLoader = true;
        $this->loader       = $loader;
        $this->discardEnvironment();
        $this->discovery->invalidate();
        $this->log->debug('event=twig.loader.replaced loader=' . $loader::class);

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
        $this->globals[$name] = $value;
        $this->createTwig();
        $this->twig->addGlobal($name, $value);
    }

    protected function addFunctions(): void
    {
        if ($this->functions_added) {
            return;
        }

        foreach ($this->functions_asis as $function) {
            if (function_exists($function)) {
                $this->twig->addFunction(new TwigFunction($function, $function));
            }
        }

        foreach ($this->functions_safe as $function) {
            if (function_exists($function)) {
                $this->twig->addFunction(new TwigFunction($function, $function, ['is_safe' => ['html']]));
            }
        }

        foreach ($this->filters as $name => $filter) {
            $this->twig->addFilter(new TwigFilter($name, $filter, ['is_variadic' => true, 'is_safe' => ['html']]));
        }
        $this->dynamicRegistry->apply($this->twig);
        $this->addFrameworkFunctions($this->twig);
        $this->functions_added = true;
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
        $this->dynamicRegistry->registerFunction($name, $callable, $options, null, false);
        if ($this->functions_added) {
            $this->discardEnvironment();
        }

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
        $this->dynamicRegistry->registerFilter($name, $callable, $options, null, false);
        if ($this->functions_added) {
            $this->discardEnvironment();
        }

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
        $cache = $this->config['cache'] ?? false;

        return is_string($cache) ? $cache : '';
    }

    /**
     * Clears compiled Twig templates. If $reinitialize is true, resets
     * the Twig Environment so new templates will be recompiled lazily.
     * Returns number of removed files.
     */
    public function clearCache(bool $reinitialize = false): int
    {
        $this->createTwig();
        $removed = 0;
        $cache   = $this->twig->getCache(false);
        if ($cache instanceof ClearableCacheInterface) {
            $removed = $cache->clear();
        }
        $this->state->clear();

        foreach (array_keys($this->cacheManager->getCompiledTemplates()) as $name) {
            $this->cacheManager->forget($name);
        }
        $this->lastWarmup            = $this->lastInvalidation = null;
        $this->cumulativeInvalidated = 0;
        $this->discovery->invalidate();
        if ($reinitialize) {
            $this->rebuildEnvironment(! ($cache instanceof RefreshableCacheInterface));
        }

        $this->log->info('event=twig.cache.cleared', ['removed' => $removed]);

        return $removed;
    }

    /**
     * Invalidate shared and current-runtime artifacts through the native cache API.
     * Returns the number of compiled artifacts removed.
     */
    public function invalidateTemplate(string $logicalName, bool $reinitialize = false): int
    {
        $logicalName = TemplateNameValidator::assertValid($logicalName);
        $this->prepareEnvironment();
        $removed = $this->invalidator->invalidateOne($logicalName, $this->twig, $reinitialize, fn () => $this->resetTwig(), fn ($level, $message) => $this->log->log($level, $message));
        $this->recordInvalidation('single', $removed, $reinitialize);

        return $removed;
    }

    /**
     * Precompiles (warms) a list of logical template names (without extension).
     * Skips templates whose compiled cache already exists unless $force = true.
     * Returns array with keys: compiled (int), skipped (int), errors (int).
     *
     * @param list<string> $templates
     */
    /**
     * @phpstan-impure
     */
    public function warmup(array $templates, bool $force = false): array
    {
        return $this->warmTemplates($templates, $force, false);
    }

    private function warmTemplates(array $templates, bool $force, bool $all): array
    {
        $this->prepareEnvironment();
        $this->loadCompileIndex();
        $summary = $this->warmer->run($this->twig, $templates, $this->extension, $force);
        $this->saveCompileIndex();
        $this->lastWarmup = ['summary' => $summary, 'all' => $all, 'timestamp' => microtime(true)];
        $this->saveWarmupSummary();
        if ($this->options->onWarmup !== null) {
            ($this->options->onWarmup)($summary + ['mode' => $all ? 'all' : 'subset', 'template_count' => count($templates)]);
        }

        return $summary;
    }

    /**
     * Attempts to warm all templates discovered in configured loader paths.
     * Only works for FilesystemLoader. Non-recursive by namespace; recursively scans directories.
     */
    /**
     * @phpstan-impure
     */
    public function warmupAll(bool $force = false): array
    {
        $this->createTwig();

        return $this->warmTemplates($this->listAllLogicalTemplates(), $force, true);
    }

    /**
     * Registers a Twig Extension dynamically. If the Environment already exists
     * the extension is added immediately, otherwise it is queued.
     *
     * @param class-string $extensionFqcn
     */
    public function registerExtension(string $extensionFqcn): self
    {
        if (! is_a($extensionFqcn, ExtensionInterface::class, true)) {
            throw new InvalidArgumentException('Twig extensions must implement ExtensionInterface.');
        }
        if (! in_array($extensionFqcn, $this->pendingExtensions, true) && ! in_array($extensionFqcn, $this->extensions, true)) {
            $this->pendingExtensions[] = $extensionFqcn;
            $this->discardEnvironment();
        }

        return $this;
    }

    /**
     * Unregister a previously registered Twig Extension (dynamic only, not those from config).
     */
    public function unregisterExtension(string $extensionFqcn): bool
    {
        $index = array_search($extensionFqcn, $this->pendingExtensions, true);
        if ($index === false) {
            return false;
        }
        array_splice($this->pendingExtensions, $index, 1);
        $this->discardEnvironment();

        return true;
    }

    /**
     * Unregister a dynamically registered function.
     */
    public function unregisterFunction(string $name): bool
    {
        $removed = $this->dynamicRegistry->unregisterFunction($name);
        if ($removed) {
            $this->discardEnvironment();
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
            $this->discardEnvironment();
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
        $this->discardEnvironment();
        $this->log->log('info', 'event=twig.cache.disabled');
    }

    /**
     * Enable template cache (optionally with custom path).
     */
    public function enableCache(?string $path = null): void
    {
        $options        = clone $this->options;
        $options->paths = $this->paths;
        if ($path !== null) {
            $options->cachePath     = $path;
            $options->compiledCache = null;
        }
        if ($options->compiledCache === null && $options->cachePath === false) {
            throw new InvalidArgumentException('Provide a cache directory when enabling cache.');
        }
        $this->initialize($options);
        $this->environmentResets++;
    }

    public function isCacheEnabled(): bool
    {
        return ! empty($this->config['cache']);
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
        $strategyStr = is_bool($strategy) ? ($strategy ? 'true' : 'false') : (string) $strategy;
        $this->log->log('info', 'event=twig.autoescape.namespace.set namespace=' . $namespace . ' strategy=' . $strategyStr);

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
        $this->log->log('info', 'event=twig.autoescape.namespace.removed namespace=' . $namespace);

        return true;
    }

    /**
     * Dynamically add a template path at runtime (optionally with a namespace).
     * If the Twig Environment is already created, the loader is updated immediately
     * and discovery cache invalidated so subsequent warmup/list operations see it.
     *
     * Examples:
     *   $twig->addPath('/project/admin/views', 'corporateCookies');
     *   $twig->addPath('/project/shared/views'); // main namespace
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
        $this->log->log('info', 'event=twig.path.added path=' . $path . ' namespace=' . $namespace);

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
        $map      = $this->autoescapeNamespaceMap;
        $strategy = static function (string $name) use ($map): bool|string {
            if (preg_match('/^@([^\/]+)\//', $name, $matches) === 1) {
                return $map[$matches[1]] ?? 'html';
            }

            return 'html';
        };
        $this->twig->getExtension(EscaperExtension::class)->setDefaultStrategy($map === [] ? 'html' : $strategy);
    }

    /**
     * Get path to compile index file.
     */
    public function getCompileIndexPath(): string
    {
        return $this->state->path('compile.index');
    }

    private function loadCompileIndex(): void
    {
        if ($this->compileIndexLoaded) {
            return;
        }
        $data  = $this->readState('compile.index') ?? [];
        $names = [];

        foreach ($data as $name => $compiled) {
            if (is_string($name) && ($compiled === true || $compiled === 1)) {
                $names[] = $name;
            }
        }
        $this->cacheManager->seedCompiled($names);
        $this->compileIndexLoaded = true;
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
        $i = 1;

        foreach ($compiledFiles as $_) {
            $this->cacheManager->markCompiled('unknown_' . $i++);
        }
        // Persist synthetic index (best-effort)
        $this->saveCompileIndex();
        $this->reconstructedIndex = true;
    }

    private function saveCompileIndex(): void
    {
        $this->writeState('compile.index', $this->cacheManager->getCompiledTemplates());
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
                if (preg_match($regex, (string) $candidate) === 1) {
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
        $names = TemplateNameValidator::filterValid($logicalNames, function (string $raw, string $error): void {
            $this->log->warning('event=twig.invalidate.skipped', ['template' => $raw, 'reason' => $error]);
        });
        $this->prepareEnvironment();
        $result = $this->invalidator->invalidateMany($names, $this->twig, $reinitialize, fn () => $this->resetTwig(), fn ($level, $message) => $this->log->log($level, $message));
        $this->recordInvalidation('batch', $result['removed'], $reinitialize);

        return $result;
    }

    public function invalidateNamespace(?string $namespace, bool $reinitialize = false): array
    {
        $namespace = TemplateNameValidator::assertValidNamespace($namespace);
        $this->prepareEnvironment();
        if (! $this->loader instanceof FilesystemLoader) {
            return ['removed' => 0, 'templates' => [], 'reinit' => false];
        }
        $result = $this->invalidator->invalidateNamespace($namespace, $this->twig, $reinitialize, $this->loader, fn () => $this->resetTwig(), fn ($level, $message) => $this->log->log($level, $message));
        $this->recordInvalidation('namespace', $result['removed'], $reinitialize);

        return $result;
    }

    private function recordInvalidation(string $type, int $removed, bool $reinitialize): void
    {
        $this->saveCompileIndex();
        if ($removed > 0) {
            $this->cumulativeInvalidated += $removed;
            $this->lastInvalidation = ['type' => $type, 'removed' => $removed, 'reinit' => $reinitialize, 'timestamp' => microtime(true)];
            $this->saveInvalidationsState();
        }
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
        try {
            $this->loadCompileIndex();
            $this->rebuildCompileIndexIfEmpty();
        } catch (Throwable $e) {
            $this->logSwallowed('diagnostics.compile_index.load', $e);
        }
        if ($this->lastWarmup === null) {
            $this->loadWarmupSummary();
        }
        if ($this->lastInvalidation === null && $this->cumulativeInvalidated === 0) {
            $this->loadInvalidationsState();
        }
        $medium      = $this->state->medium();
        $diagnostics = [
            'renders'            => $this->renderCount,
            'last_render_view'   => $this->lastRenderView,
            'environment_resets' => $this->environmentResets,
            'cache'              => [
                'enabled'             => $this->isCacheEnabled(),
                'path'                => $this->isCacheEnabled() ? $this->getCachePath() : null,
                'mode'                => $this->usingCacheService ? 'service' : 'filesystem',
                'service_class'       => $this->store?->backend(),
                'prefix'              => $this->cachePrefix,
                'ttl'                 => $this->cacheTtl,
                'compiled_templates'  => count($this->cacheManager->getCompiledTemplates()),
                'reconstructed_index' => $this->reconstructedIndex,
            ],
            'performance' => [
                'total_render_time_ms' => round($this->totalRenderTime * 1000, 2),
                'avg_render_time_ms'   => $this->renderCount ? round($this->totalRenderTime * 1000 / $this->renderCount, 2) : 0.0,
            ],
            'capabilities' => $this->capabilities,
            'persistence'  => ['compile_index' => ['medium' => $medium]],
        ];
        if ($this->capabilities['dynamicMetrics']) {
            $diagnostics['dynamic_functions'] = $this->dynamicRegistry->getFunctionCounts();
            $diagnostics['dynamic_filters']   = $this->dynamicRegistry->getFilterCounts();
        }
        if ($this->capabilities['extendedDiagnostics']) {
            $diagnostics['static_functions']             = ['configured' => count($this->functions_asis) + count($this->functions_safe)];
            $diagnostics['static_filters']               = ['configured' => count($this->filters)];
            $diagnostics['extensions']                   = ['configured' => count($this->extensions), 'pending' => count($this->pendingExtensions)];
            $diagnostics['performance']['per_template']  = $this->renderProfiler->snapshot();
            $diagnostics['performance']['top_templates'] = $this->renderProfiler->topByTotal(10);
            $diagnostics['names']                        = [
                'static_functions'  => array_slice(array_merge($this->functions_asis, $this->functions_safe), 0, 50),
                'dynamic_functions' => array_slice($this->dynamicRegistry->listFunctionNames(), 0, 50),
                'static_filters'    => array_slice(array_keys($this->filters), 0, 50),
                'dynamic_filters'   => array_slice($this->dynamicRegistry->listFilterNames(), 0, 50),
            ];
        }
        if ($this->capabilities['warmupSummary']) {
            $diagnostics['warmup']                = $this->lastWarmup;
            $diagnostics['persistence']['warmup'] = ['medium' => $medium];
        }
        if ($this->capabilities['invalidationHistory']) {
            $diagnostics['invalidations']                = ['last' => $this->lastInvalidation, 'cumulative_removed' => $this->cumulativeInvalidated];
            $diagnostics['persistence']['invalidations'] = ['medium' => $medium];
        }
        if ($this->capabilities['discoverySnapshot']) {
            $this->discovery->loadPersisted();
            $diagnostics['discovery']                         = $this->discovery->getStats() + ['persistence_medium' => $medium];
            $diagnostics['persistence']['discovery_snapshot'] = ['medium' => $medium];
        }

        return $diagnostics;
    }

    public function resetDiagnostics(bool $includeIndex = false, bool $includeCache = false): array
    {
        $removed    = $failed = [];
        $cacheFiles = 0;
        if ($includeCache) {
            try {
                $cacheFiles = $this->clearCache(true);
            } catch (Throwable $e) {
                $failed[] = 'compiled_cache';
                $this->logSwallowed('diagnostics.reset.cache', $e);
            }
        } else {
            $keys = ['disc.stats', 'disc.list', 'warmup.summary'];
            if ($includeIndex) {
                $keys[] = 'compile.index';
                $keys[] = 'compile.sources';
            }

            foreach ($keys as $key) {
                $label = $this->state->path($key) ?: ($this->cachePrefix . $key);

                try {
                    $present = $this->state->read($key) !== null;
                    if (! $this->state->delete($key)) {
                        $failed[] = $label;
                    } elseif ($present) {
                        $removed[] = $label;
                    }
                } catch (Throwable $e) {
                    $failed[] = $label;
                    $this->logSwallowed('diagnostics.reset.state', $e);
                }
            }
            if ($includeIndex) {
                foreach (array_keys($this->cacheManager->getCompiledTemplates()) as $name) {
                    $this->cacheManager->forget($name);
                }
                $this->compileIndexLoaded = true;
                $this->reconstructedIndex = false;
            }
            $this->lastWarmup = null;
            $this->discovery->invalidate();
        }

        return [
            'removed_files' => $removed, 'failed' => $failed, 'cache_files_removed' => $cacheFiles,
            'include_index' => $includeIndex, 'include_cache' => $includeCache, 'ok' => $failed === [],
        ];
    }

    private function readState(string $key): ?array
    {
        try {
            return $this->state->read($key);
        } catch (Throwable $e) {
            $this->logSwallowed('state.read.' . $key, $e);

            return null;
        }
    }

    /**
     * Path to invalidations state file (file backend).
     */
    private function writeState(string $key, array $payload): void
    {
        try {
            $this->state->write($key, $payload);
        } catch (Throwable $e) {
            $this->logSwallowed('state.write.' . $key, $e);
        }
    }

    /**
     * Persist invalidations state (last + cumulative)
     */
    private function saveInvalidationsState(): void
    {
        if ($this->capabilities['invalidationHistory']) {
            $this->writeState('invalidations', ['last' => $this->lastInvalidation, 'cumulative' => $this->cumulativeInvalidated, 'version' => 1]);
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
        $data = $this->readState('invalidations');
        if (is_array($data)) {
            $this->cumulativeInvalidated = is_int($data['cumulative'] ?? null) ? $data['cumulative'] : 0;
            $this->lastInvalidation      = is_array($data['last'] ?? null) ? $data['last'] : null;
        }
    }

    /**
     * Persist last warmup summary (best-effort).
     */
    private function saveWarmupSummary(): void
    {
        if ($this->capabilities['warmupSummary'] && $this->lastWarmup !== null) {
            $this->writeState('warmup.summary', $this->lastWarmup + ['version' => 1]);
        }
    }

    /**
     * Load persisted warmup summary if exists.
     */
    private function loadWarmupSummary(): void
    {
        if ($this->capabilities['warmupSummary']) {
            $data = $this->readState('warmup.summary');
            if (is_array($data['summary'] ?? null)) {
                $this->lastWarmup = $data;
            }
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
     * no-op logging when no logger is configured.
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
}
