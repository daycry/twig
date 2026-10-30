<?php

namespace Daycry\Twig\CodeIgniter4;

use CodeIgniter\Cache\Handlers\FileHandler;
use CodeIgniter\Events\Events;
use CodeIgniter\Filters\DebugToolbar;
use Config\Services;
use Config\Toolbar;
use Daycry\Twig\CodeIgniter4\Cache\CacheStore;
use Daycry\Twig\CodeIgniter4\Cache\CICacheAdapter;
use Daycry\Twig\CodeIgniter4\Config\Twig as TwigConfig;
use Daycry\Twig\CodeIgniter4\Debug\Toolbar\Collectors\Twig as TwigCollector;
use Daycry\Twig\Configuration;
use Daycry\Twig\Twig as CoreTwig;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\TwigFunction;

class Twig extends CoreTwig
{
    public function __construct(?TwigConfig $config = null, ?LoggerInterface $logger = null)
    {
        [$options, $store] = $this->resolveConfiguration($config ?? config(TwigConfig::class));
        parent::__construct($options, $logger ?? Services::logger(), $store);
    }

    private function resolveConfiguration(TwigConfig $config): array
    {
        $options              = $config->toCoreConfig();
        $cacheConfig          = config('Cache');
        $options->cachePrefix = $cacheConfig->prefix !== '' && str_ends_with(trim($cacheConfig->prefix), '_') ? '_twig_' : 'twig_';
        $cache                = Services::cache();
        $store                = null;
        if (! $cache instanceof FileHandler) {
            $store = new CacheStore($cache);
            if ($options->cachePath !== false) {
                $options->compiledCache = new CICacheAdapter($cache, $options->cachePrefix, $options->cacheTtl);
            }
        }
        $callback          = $options->onWarmup;
        $options->onWarmup = static function (array $summary) use ($callback): void {
            if ($callback !== null) {
                $callback($summary);
            }
            Events::trigger('twig:warmup:after', $summary);
        };

        return [$options, $store];
    }

    public function initialize(Configuration|TwigConfig|null $config = null): CoreTwig
    {
        if ($config instanceof Configuration) {
            return parent::initialize($config);
        }
        [$options, $store] = $this->resolveConfiguration($config ?? config(TwigConfig::class));
        $this->store       = $store;

        return parent::initialize($options);
    }

    protected function addFunctions(): void
    {
        if (! $this->functions_added) {
            helper(['form', 'security', 'url', 'text']);
            parent::addFunctions();
        }
    }

    protected function addFrameworkFunctions(Environment $environment): void
    {
        $environment->addFunction(new TwigFunction('anchor', $this->safe_anchor(...), ['is_safe' => ['html']]));
        $environment->addFunction(new TwigFunction('validation_list_errors', $this->validation_list_errors(...), ['is_safe' => ['html']]));
    }

    public function safe_anchor($uri = '', $title = '', $attributes = []): string
    {
        $escapedAttributes = [];

        foreach ($attributes as $key => $value) {
            $escapedAttributes[esc($key)] = $value;
        }

        return anchor(esc($uri, 'url'), esc($title), $escapedAttributes);
    }

    public function validation_list_errors(): string
    {
        return Services::validation()->listErrors();
    }

    protected function afterRender(string $output, string $view, float $start): string
    {
        if (! $this->debug) {
            return $output;
        }
        $filters  = service('filters');
        $required = ($filters->getRequiredFilters('after')[0] ?? []);
        $enabled  = in_array('toolbar', $required, true)
            || in_array(DebugToolbar::class, ($filters->getFiltersClass()['after'] ?? []), true);
        if ($enabled) {
            $this->logPerformance($start, microtime(true), $view);
            if (in_array(TwigCollector::class, config(Toolbar::class)->collectors, true)) {
                return '<!-- DEBUG-VIEW START ' . $view . ' -->' . PHP_EOL . $output . PHP_EOL . '<!-- DEBUG-VIEW ENDED ' . $view . ' -->' . PHP_EOL;
            }
        }

        return $output;
    }
}
