<?php

namespace Daycry\Twig\Contracts;

use Twig\Loader\FilesystemLoader;

/**
 * Discovery service contract: enumerates logical template names and tracks
 * discovery cache statistics. Implementations are expected to be process-local
 * (not thread-safe) and may opt-in to filesystem or remote cache snapshots.
 */
interface DiscoveryInterface
{
    /**
     * @return list<string> logical template names (without extension)
     */
    public function listAll(FilesystemLoader $loader, string $extension): array;

    /**
     * Reset the in-memory cache without touching persisted state.
     */
    public function invalidate(): void;

    /**
     * Returns counters and metadata used by diagnostics (`hits`, `misses`,
     * `invalidations`, `cached`, `count`, `cache_source`).
     *
     * @return array<string,mixed>
     */
    public function getStats(): array;
}
