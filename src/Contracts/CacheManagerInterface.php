<?php

namespace Daycry\Twig\Contracts;

/**
 * Compile-index manager contract. Tracks which logical templates have been
 * compiled (warmed) so subsequent operations can skip the filesystem scan.
 */
interface CacheManagerInterface
{
    /**
     * Seed the in-memory list (used after restoring an existing index).
     *
     * @param list<string> $names
     */
    public function seedCompiled(array $names): void;

    /**
     * @return array<string,bool>
     */
    public function getCompiledTemplates(): array;

    public function markCompiled(string $logical): void;

    public function forget(string $logical): void;

    public function loadIndex(string $indexPath): void;

    public function saveIndex(string $indexPath): void;

    public function isCompiled(string $logical): bool;
}
