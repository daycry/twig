<?php

namespace Daycry\Twig\Config;

/**
 * Resolved capability matrix derived from `Config\Twig::$leanMode` plus the
 * five nullable `enable*` overrides. Replacing the inline associative array
 * inside `Twig::computeCapabilities()` with a value object makes the rules
 * easier to test in isolation and harder to mistype.
 *
 * Read-only: capability resolution happens once during `Twig::initialize()`
 * and never mutates after that.
 */
final readonly class CapabilitiesProfile
{
    public function __construct(
        public bool $discoverySnapshot,
        public bool $warmupSummary,
        public bool $invalidationHistory,
        public bool $dynamicMetrics,
        public bool $extendedDiagnostics,
    ) {
    }

    /**
     * Build the profile from a config instance, applying lean defaults plus
     * nullable per-flag overrides (where `null` means "inherit profile").
     */
    public static function fromConfig(Twig $config): self
    {
        $lean      = $config->leanMode ?? false;
        $baseValue = ! $lean; // full → true, lean → false

        $resolve = static fn (?bool $override): bool => $override ?? $baseValue;

        return new self(
            $resolve($config->enableDiscoverySnapshot ?? null),
            $resolve($config->enableWarmupSummary ?? null),
            $resolve($config->enableInvalidationHistory ?? null),
            $resolve($config->enableDynamicMetrics ?? null),
            $resolve($config->enableExtendedDiagnostics ?? null),
        );
    }

    /**
     * Plain associative array shape for JSON / diagnostics output. Kept stable
     * because external monitoring may parse it.
     *
     * @return array{discoverySnapshot:bool,warmupSummary:bool,invalidationHistory:bool,dynamicMetrics:bool,extendedDiagnostics:bool}
     */
    public function toArray(): array
    {
        return [
            'discoverySnapshot'   => $this->discoverySnapshot,
            'warmupSummary'       => $this->warmupSummary,
            'invalidationHistory' => $this->invalidationHistory,
            'dynamicMetrics'      => $this->dynamicMetrics,
            'extendedDiagnostics' => $this->extendedDiagnostics,
        ];
    }
}
