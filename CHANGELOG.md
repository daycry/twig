# Changelog

All notable changes to this project will be documented in this file.

## [3.1.0] - 2025-09-19
### Added
- Automatic discovery snapshot + preload + APCu acceleration in full profile (no manual flags needed).
- Lean Mode capability profile with nullable overrides (`enableDiscoverySnapshot`, `enableWarmupSummary`, `enableInvalidationHistory`, `enableDynamicMetrics`, `enableExtendedDiagnostics`).
- Autodetected cache backend (CI cache service preferred, filesystem fallback) with automatic prefix derivation.
- Expanded diagnostics: render timing, discovery cache source, warmup summary, invalidation history (gated by capabilities).

### Changed
- Configuration surface simplified: discovery micro-tuning flags removed from docs; behavior is profile-driven.
- Snapshot persistence: always on in full profile; opt-in via override in Lean Mode.
- Fingerprint strategy depth fixed (previous configurable depth removed).

### Removed
- Legacy documentation of tuning flags and deprecated cache backend keys (docs now show only current configuration model).

### Guidance
Use `leanMode = false` for maximum automatic acceleration & observability. Use `leanMode = true` for minimal overhead, optionally re‑enabling specific capabilities with the nullable overrides.

## [3.0.0] - 2025-09-18
### Added
- Modular services: `TemplateDiscovery`, `TemplateCacheManager`, `DynamicRegistry`, `TemplateInvalidator`.
- Documentation: Added `docs/SERVICES.md` detailed architecture & advanced usage guide.
- Batch & namespace invalidation APIs: `invalidateTemplates()`, `invalidateNamespace()` + CLI `twig:invalidate:batch`.
- Unregister APIs: `unregisterFunction()`, `unregisterFilter()`, `unregisterExtension()`.
- Runtime cache toggle: `disableCache()`, `enableCache()`, `isCacheEnabled()`.
- Namespace auto-escape mapping: `setAutoescapeForNamespace()`, `removeAutoescapeForNamespace()`.
- Persistent compile index & listing: `listTemplates()` with status and `compile-index.json` storage.
- CLI utilities: `twig:list`, `twig:stats`.
- Pattern & namespace filtering in `listTemplates()`.
### Changed
- Internal monolithic `Twig` class refactored into facade delegating to new services.
- Batch invalidation optimized to single directory traversal.
- Logging standardized to `event=twig.* key=value` format using global `log_message()`.
- README expanded for new runtime, listing & invalidation features.
### Removed
- Internal PSR-3 logger property & `setLogger()` method (replaced by helper logging).
- Direct access to internal compiled template arrays (now encapsulated in services).

## [0.2.0] - 2025-09-18
### Added
- PSR-3 logger integration (`setLogger()`) with lifecycle event messages.
- (Removed later) Previously introduced internal min-level and CI threshold mapping; logging now relies solely on CodeIgniter logger configuration.
- Warmup APIs (`warmup()`, `warmupAll()`) and CLI command `twig:warmup`.
- Dynamic extension registration (`registerExtension()`).
- Selective template invalidation (`invalidateTemplate()`) + CLI `twig:invalidate`.
- Cache utilities: `getCachePath()`, `clearCache()` + CLI `twig:clear-cache`.
- Dynamic runtime registration of functions & filters (`registerFunction()`, `registerFilter()`) with option arrays.
- Strict variables support via config `$strictVariables`.
- Loader replacement fluent API `withLoader()`.

### Changed
- Internal environment recreation logic when late-registering extensions.
- Deduplication utility preserves associative keys.
- Simplified logging: dropped custom Twig logging configuration flags in favor of native CodeIgniter logger settings. Later removed `setLogger()` entirely (moved to helper-based logging in Unreleased).

### Documentation
- Expanded README with sections on dynamic registration, cache management, extensions, invalidation, logging.

### Tests
- Added comprehensive test coverage for new features including logger behavior and min level filtering.

## [0.1.0] - 2025-08-XX
Initial extracted functionality with basic Twig integration for CodeIgniter 4.
