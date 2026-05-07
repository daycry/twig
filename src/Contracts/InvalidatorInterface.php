<?php

namespace Daycry\Twig\Contracts;

use Twig\Loader\FilesystemLoader;

/**
 * Cache invalidation contract. Removes compiled template artifacts, optionally
 * resets the surrounding Twig environment, and emits structured log lines via
 * the supplied logger callable.
 *
 * The `$resetTwig` and `$log` callables are passed in (rather than wired via
 * dependency) to keep the invalidator framework-neutral and easy to fake in
 * tests.
 */
interface InvalidatorInterface
{
    public function invalidateOne(string $logicalName, string $cacheDir, bool $reinitialize, callable $resetTwig, callable $log): int;

    /**
     * @param list<string> $logicalNames
     *
     * @return array{removed:int,templates:array<string,int>,reinit:bool}
     */
    public function invalidateMany(array $logicalNames, string $cacheDir, bool $reinitialize, callable $resetTwig, callable $log): array;

    /**
     * @return array{removed:int,templates:array<string,int>,reinit:bool}
     */
    public function invalidateNamespace(?string $namespace, string $cacheDir, bool $reinitialize, FilesystemLoader $loader, callable $resetTwig, callable $log): array;
}
