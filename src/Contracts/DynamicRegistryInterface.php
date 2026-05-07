<?php

namespace Daycry\Twig\Contracts;

use Twig\Environment;

/**
 * Runtime registration of Twig functions and filters. Items added before the
 * Environment exists are queued and applied on `apply()`.
 */
interface DynamicRegistryInterface
{
    /**
     * @param array<string,mixed> $options
     */
    public function registerFunction(string $name, callable $callable, array $options, ?Environment $env, bool $envReady): void;

    /**
     * @param array<string,mixed> $options
     */
    public function registerFilter(string $name, callable $callable, array $options, ?Environment $env, bool $envReady): void;

    public function apply(Environment $env): void;

    public function unregisterFunction(string $name): bool;

    public function unregisterFilter(string $name): bool;

    /**
     * @return array<string,int>
     */
    public function getFunctionCounts(): array;

    /**
     * @return array<string,int>
     */
    public function getFilterCounts(): array;

    /**
     * @return list<string>
     */
    public function listFunctionNames(): array;

    /**
     * @return list<string>
     */
    public function listFilterNames(): array;
}
