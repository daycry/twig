<?php

namespace Daycry\Twig\Contracts;

/**
 * String storage for optional persisted metadata, independent of a framework.
 */
interface CacheStoreInterface
{
    public function get(string $key): ?string;

    public function save(string $key, string $value, int $ttl): bool;

    /**
     * Successful deletion includes an already absent key.
     */
    public function delete(string $key): bool;

    public function medium(): string;

    public function backend(): string;
}
