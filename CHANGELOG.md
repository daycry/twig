# Changelog

All notable changes to this project will be documented in this file. The
format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
the project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **PSR-3 logger injection.** Pass an optional `Psr\Log\LoggerInterface` to the
  `Twig` constructor (or `setLogger()` later) to redirect structured log output
  to monolog/syslog/etc. When unset the library still falls back to the
  CodeIgniter `log_message()` helper.
- **`twig:lint`** CLI command. Validates Twig syntax without rendering — wraps
  `Environment::tokenize()` + `parse()` so it is safe in CI/CD.
- **`twig:doctor`** CLI command. Health-check that reports missing template
  paths, unwritable cache directories, missing/disabled APCu, and reconstructed
  compile indexes. Exits non-zero when there is at least one error-level finding.
- **Per-template render profiler.** When `extendedDiagnostics` is on,
  `getDiagnostics()['performance']` now includes `per_template` and
  `top_templates` lists with `count`, `total_ms`, `avg_ms`, `max_ms`.
- **Service interfaces** in `Daycry\Twig\Contracts\` — `DiscoveryInterface`,
  `CacheManagerInterface`, `InvalidatorInterface`, `DynamicRegistryInterface` —
  enabling test doubles and alternate implementations.
- **Public enums** in `Daycry\Twig\Constants\` — `TwigEvent`, `TwigCacheKey`,
  `CacheSource` — replacing magic strings.
- **`AbstractTwigCommand`** base class for CLI commands consolidating the
  shared group, service resolution, flag parsing, and JSON output helpers.
- **`Daycry\Twig\Support\TemplateNameValidator`** — centralized
  validator that rejects `..`, leading slashes, null bytes and
  out-of-charset characters in template names supplied to public APIs and CLI.
- **`Daycry\Twig\Support\PersistenceDecoder`** — defensive JSON decoder used
  when reading compile-index, warmup-summary, invalidations and discovery
  snapshots; tampered or wrong-typed payloads return `null` instead of crashing.
- **`tests/_support/Traits/TwigTestSetup.php`** — shared trait with
  `setupTwigWithTemplates()`, `setupTwigWithInMemory()`, `cleanCacheDir()`.
- **Documentation:** `docs/TROUBLESHOOTING.md`, `docs/DIAGNOSTICS_REFERENCE.md`,
  `CONTRIBUTING.md`.

### Changed
- **PHP requirement bumped to ≥ 8.2.** Enables `readonly` properties, `enum`,
  intersection types, first-class callable, DNF types. CI matrix runs on 8.2,
  8.3, 8.4 and 8.5; `composer.json` and `rector.php` reflect the new floor.
- **CICacheAdapter signs payloads with HMAC-SHA256.** Compiled-template entries
  written through the CI cache backend are now authenticated; tampered or
  unsigned legacy entries are dropped (and logged) instead of being `eval()`ed.
  HMAC key derives from `Config\Encryption::$key` when available, falling back
  to a stable per-install value.
- **Batch warmup is O(N) instead of O(N · files).** `warmup()` now scans the
  compile cache once into a `hash → present` map.
- **CLI commands return integer exit codes** (`EXIT_SUCCESS`, `EXIT_ERROR`,
  `EXIT_USER_INPUT`). `twig:publish` no longer calls `exit()` on internal errors.
- **`TwigReset`** is now a thin alias of `TwigResetMetrics` (zero-duplication).
- **Discovery fingerprint** uses `ksort` + `crc32(json_encode(...))` over the
  per-directory mtime samples instead of XOR (which collapsed identical values).
- **Compile-index file** is now written without `JSON_PRETTY_PRINT` (smaller
  on disk; format unchanged for readers).

### Fixed
- Hash-based template invalidation (`invalidateTemplate`,
  `invalidateTemplates`) now anchors the match to the file basename, preventing
  spurious matches against substrings appearing elsewhere in the absolute path.
- Many `catch (Throwable $e) {}` blocks across `Twig`, `TemplateDiscovery`,
  `CICacheAdapter`, `TemplateCacheManager` now log at debug level instead of
  swallowing failures silently.
- `loadInvalidationsState()` and `loadWarmupSummary()` validate the type of
  decoded values before assigning them, avoiding fatal errors when the cache
  contains corrupted or older-shape payloads.
- `TwigCachePrefixNormalizationTest` no longer emits PHP 8.5 deprecation
  warnings (`ReflectionProperty::setAccessible()`).

### Security
- HMAC verification of CI-cached compiled templates closes a defense-in-depth
  gap where a compromised cache backend (e.g. unauth'd Redis, key collision)
  could feed arbitrary PHP into `eval()`.
- Template names supplied to `addPath()`, `invalidateTemplate*()`,
  `invalidateNamespace()` and the matching CLI commands are now validated.
  Path-traversal sequences (`..`, leading `/`, null bytes) are rejected with
  a clear error before reaching filesystem operations.

## [3.1.1] - 2025-09-22
### Changed
- Cache key prefix derivation simplified again: only two possible prefixes now: `twig_` (default) or `_twig_` (when global `Config\\Cache::$prefix` ends with an underscore). Global textual prefix is no longer embedded in Twig keys.

### Removed
- `Config\\Twig::$cachePrefix` option (previous override) remains removed; prior intermediate deterministic rule using the textual global prefix replaced by the minimal two-form rule (prevents duplication like `mediaprous_mediaprous_twig_` and shortens keys).

### Migration Notes
If you previously relied on keys containing the project prefix (e.g. `app_twig_...`) update any external tooling to look for `twig_` or `_twig_`. Delete any stale entries with old prefixes manually if desired; Twig will regenerate as used. Diagnostics expose the active prefix at `getDiagnostics()['cache']['prefix']`.

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
