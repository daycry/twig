<?php

namespace Daycry\Twig\Contracts;

/**
 * Refresh once per render/warmup, returning true when loaded templates are obsolete.
 */
interface RefreshableCacheInterface
{
    public function refresh(): bool;

    /**
     * Stable, locally cached identity for the currently selected backend generation.
     */
    public function revision(): string;
}
