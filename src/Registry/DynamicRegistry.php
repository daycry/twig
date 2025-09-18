<?php

namespace Daycry\Twig\Registry;

use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Manages dynamic (runtime) Twig functions & filters registration.
 * Keeps track of queued definitions prior to Environment creation and
 * applies them when requested. Framework logging delegated via global log_message helper.
 */
class DynamicRegistry
{
    /**
     * @var array<string,array{callable:callable,options:array}>
     */
    private array $functions = [];

    /**
     * @var array<int,array{name:string,callable:callable,options:array}>
     */
    private array $pendingFunctions = [];

    /**
     * @var array<string,array{callable:callable,options:array}>
     */
    private array $filters = [];

    /**
     * @var array<int,array{name:string,callable:callable,options:array}>
     */
    private array $pendingFilters = [];

    /**
     * Queue or immediately register a dynamic function.
     */
    public function registerFunction(string $name, callable $callable, array $options, ?Environment $env, bool $envReady): void
    {
        if ($env !== null && $envReady) {
            $env->addFunction(new TwigFunction($name, $callable, $options));
            $this->functions[$name] = ['callable' => $callable, 'options' => $options];
            if (function_exists('log_message')) {
                log_message('info', 'event=twig.function.registered name=' . $name);
            }

            return;
        }
        $this->pendingFunctions[] = [
            'name'     => $name,
            'callable' => $callable,
            'options'  => $options,
        ];
        if (function_exists('log_message')) {
            log_message('info', 'event=twig.function.queued name=' . $name);
        }
    }

    /**
     * Queue or immediately register a dynamic filter.
     */
    public function registerFilter(string $name, callable $callable, array $options, ?Environment $env, bool $envReady): void
    {
        if ($env !== null && $envReady) {
            $env->addFilter(new TwigFilter($name, $callable, $options));
            $this->filters[$name] = ['callable' => $callable, 'options' => $options];
            if (function_exists('log_message')) {
                log_message('info', 'event=twig.filter.registered name=' . $name);
            }

            return;
        }
        $this->pendingFilters[] = [
            'name'     => $name,
            'callable' => $callable,
            'options'  => $options,
        ];
        if (function_exists('log_message')) {
            log_message('info', 'event=twig.filter.queued name=' . $name);
        }
    }

    /**
     * Apply all queued dynamic functions & filters to environment (idempotent).
     */
    public function apply(Environment $env): void
    {
        // already registered dynamic sets (re-adding is safe but avoid duplicates)
        foreach ($this->functions as $name => $meta) {
            // ensure not already present in env? Adding again would override - fine.
            $env->addFunction(new TwigFunction($name, $meta['callable'], $meta['options']));
        }

        foreach ($this->filters as $name => $meta) {
            $env->addFilter(new TwigFilter($name, $meta['callable'], $meta['options']));
        }
        // apply pending then promote to permanent maps
        if ($this->pendingFunctions) {
            foreach ($this->pendingFunctions as $fn) {
                $env->addFunction(new TwigFunction($fn['name'], $fn['callable'], $fn['options']));
                $this->functions[$fn['name']] = ['callable' => $fn['callable'], 'options' => $fn['options']];
            }
            $this->pendingFunctions = [];
        }
        if ($this->pendingFilters) {
            foreach ($this->pendingFilters as $f) {
                $env->addFilter(new TwigFilter($f['name'], $f['callable'], $f['options']));
                $this->filters[$f['name']] = ['callable' => $f['callable'], 'options' => $f['options']];
            }
            $this->pendingFilters = [];
        }
    }

    /**
     * Remove a dynamic function (returns true if removed).
     */
    public function unregisterFunction(string $name): bool
    {
        $removed = false;
        if (isset($this->functions[$name])) {
            unset($this->functions[$name]);
            $removed = true;
        }
        if ($this->pendingFunctions) {
            $this->pendingFunctions = array_values(array_filter($this->pendingFunctions, static fn ($f) => $f['name'] !== $name));
        }
        if ($removed && function_exists('log_message')) {
            log_message('info', 'event=twig.function.unregistered name=' . $name);
        }

        return $removed;
    }

    /**
     * Remove a dynamic filter (returns true if removed).
     */
    public function unregisterFilter(string $name): bool
    {
        $removed = false;
        if (isset($this->filters[$name])) {
            unset($this->filters[$name]);
            $removed = true;
        }
        if ($this->pendingFilters) {
            $this->pendingFilters = array_values(array_filter($this->pendingFilters, static fn ($f) => $f['name'] !== $name));
        }
        if ($removed && function_exists('log_message')) {
            log_message('info', 'event=twig.filter.unregistered name=' . $name);
        }

        return $removed;
    }

    /**
     * Return function counts for diagnostics.
     */
    public function getFunctionCounts(): array
    {
        return [
            'active'  => count($this->functions),
            'pending' => count($this->pendingFunctions),
        ];
    }

    /**
     * Return filter counts for diagnostics.
     */
    public function getFilterCounts(): array
    {
        return [
            'active'  => count($this->filters),
            'pending' => count($this->pendingFilters),
        ];
    }

    /**
     * Return list of dynamic function names (active + pending queued) for diagnostics / tooling.
     *
     * @return list<string>
     */
    public function listFunctionNames(): array
    {
        $names = array_keys($this->functions);
        if ($this->pendingFunctions) {
            foreach ($this->pendingFunctions as $pf) {
                $names[] = $pf['name'];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Return list of dynamic filter names (active + pending queued) for diagnostics / tooling.
     *
     * @return list<string>
     */
    public function listFilterNames(): array
    {
        $names = array_keys($this->filters);
        if ($this->pendingFilters) {
            foreach ($this->pendingFilters as $pf) {
                $names[] = $pf['name'];
            }
        }

        return array_values(array_unique($names));
    }
}
