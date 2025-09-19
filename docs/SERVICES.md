# Modular Services Guide (v3.0.0)

Version 3.0.0 introduces a modular internal architecture. The public facade API (render, display, registerFunction, registerFilter, warmup, invalidate*, listTemplates, etc.) remains backward compatible. This document explains each internal service so advanced users can extend, debug, or instrument the integration without diving into a monolithic class.

## Overview

| Service | Primary Responsibility | Collaborates With | Why It Exists |
|---------|------------------------|-------------------|---------------|
| `TemplateDiscovery` | Enumerate logical template names (namespaced or root) with per-process caching | Twig `FilesystemLoader` | Avoid repeated filesystem scans; centralize path reflection |
| `TemplateCacheManager` | Persist/read compile index (`compile-index.json`) and track compiled templates in memory | Warmup, Invalidation, Listing | Know what is already compiled and skip redundant work |
| `DynamicRegistry` | Runtime registration & unregistration of functions and filters | `Twig` facade (environment bootstrap) | Modify Twig behavior without reconstructing the facade or losing queued items |
| `TemplateInvalidator` | Invalidate compiled templates (single/batch/namespace) efficiently | Discovery, CacheManager | Unified, optimized cache cleanup logic |

## 1. TemplateDiscovery

Location: `Daycry\Twig\Discovery\TemplateDiscovery`

Purpose: Given a `FilesystemLoader` and the configured template extension (`.twig` by default) it produces a de-duplicated list of logical names (e.g. `welcome`, `@admin/dashboard/index`). A per-process cache keyed by a context hash (loader class + path map + extension) prevents repeated scans.

Key Methods:
- `listAll(FilesystemLoader $loader, string $extension): array` → Returns `string[]` of logical names.
- `invalidate(): void` → Clears the in-memory cache (called automatically on loader replacement or full environment reset).

Advanced Use:
- Subclass to support exotic loaders; override how paths are collected.
- Wrap to add timing metrics for discovery operations.

### 1.1 Algorithm (FilesystemLoader)
1. Reflect `$loader->paths` (namespaces => array of base directories).
2. Canonicalize: `realpath`, normalize slashes, sort namespaces & paths.
3. Generate context hash: `md5(class|extension|json(canonical_paths_map))`.
4. Fast path: if in-memory cache present & context hash matches → HIT.
5. If preloaded snapshot present (cache filled but no `contextHash` yet): verify fingerprint → promote to HIT.
6. Else (cold): recursive directory iteration per base path collecting files ending in extension; build logical names (prepend `@ns/` when namespace != main).
7. Persist fingerprint & optionally list snapshot (if configured) + stats.

### 1.2 Fingerprint
```
fingerprint = sha1(json_encode([
    canonical_paths_map,   // sorted
    per_namespace: {
        path => sampled_mtime_hash()
    }
]))
```
`sampled_mtime_hash` XORs root directory mtimes (depth now fixed/shallow after simplification; previous configurable depth removed).

### 1.3 Performance Characteristics
| Mode | Cost | When |
|------|------|------|
| Cold Scan | O(F) where F = number of files (I/O bound) | First request / fingerprint change |
| Snapshot Restore | O(P) (paths stat + JSON decode) | Full profile or lean+override after initial scan |
| APCu Restore | O(1) key fetch + array copy | When snapshot active & APCu enabled |
| Fingerprint Verify | O(P) recompute | Automatic when snapshot active (implicit preload) |

### 1.4 Operational Guidance
| Symptom | Recommendation |
|---------|----------------|
| Frequent unnecessary scans (low hits) | Use full profile (`leanMode = false`) or force snapshot via `enableDiscoverySnapshot = true` |
| Need absolute minimum overhead | Stay in lean profile (no snapshot) |
| Large template set, want lean diagnostics | Lean + `enableDiscoverySnapshot = true` |
| Multi-process duplication | Ensure snapshot active; APCu auto-used if extension present |

### 1.5 Failure Modes (Graceful)
| Failure | Fallback |
|---------|----------|
| Corrupt snapshot JSON | Full scan |
| APCu unavailable | Snapshot or scan |
| CI cache miss | Snapshot file or scan |
| Fingerprint mismatch | Full scan + new fingerprint |

## 2. TemplateCacheManager

Location: `Daycry\Twig\Cache\TemplateCacheManager`

Responsibility: Maintain an in-memory set (`logicalName => true`) of compiled templates and persist it to `compile-index.json` in the configured cache directory.

Key Methods:
- `loadIndex(string $path): void` → Lazy loads and hydrates internal state.
- `saveIndex(string $path): void` → Persists only if state changed.
- `markCompiled(string $logical): void` → Record a template as compiled (warmup or first render).
- `forget(string $logical): void` → Remove compiled mark on invalidation.
- `isCompiled(string $logical): bool` → Fast check used by warmup & listing.
- `getCompiledTemplates(): array` → Entire set (debug/introspection).

Best Practices:
- Let the facade orchestrate `loadIndex/saveIndex`; avoid manual calls unless writing custom tooling.
- Never mutate the JSON file directly; rely on the service to prevent drift.

## 3. DynamicRegistry

Location: `Daycry\Twig\Registry\DynamicRegistry`

Purpose: Provide a unified queue + applied store for runtime function and filter additions. Items registered before the Twig `Environment` is first created are queued; once the environment exists, they are applied immediately. On environment resets they are re-applied idempotently.

Key Methods:
- `registerFunction(string $name, callable $callable, array $options, ?Environment $env, bool $envReady): void`
- `registerFilter(string $name, callable $callable, array $options, ?Environment $env, bool $envReady): void`
- `apply(Environment $env): void` → Idempotently apply queued + persisted items.
- `unregisterFunction(string $name): bool`
- `unregisterFilter(string $name): bool`

Usage Notes:
- The facade determines `envReady` (whether core built-ins already loaded) and passes the current `Environment` instance.
- You can build higher-level registrars (e.g. annotation scanners) that ultimately call these methods.

### 3.1 Options Matrix (Twig Native)
| Option | Applies To | Type | Effect |
|--------|-----------|------|--------|
| `is_safe` | function/filter | array<string> | Marks output safe for listed contexts (e.g. `['html']`) |
| `needs_environment` | function/filter | bool | First parameter becomes `\Twig\Environment` |
| `needs_context` | function/filter | bool | First parameter becomes template context array |
| `is_variadic` | function/filter | bool | Collect trailing args into array |
| `deprecated` | function/filter | string|bool | Triggers deprecation notice |
| `pre_escape` | filter | string | Force pre-escape strategy |
| `preserves_safety` | filter | array<string> | Propagate safety flags |

Backward compatible Boolean shorthand in facade:
```php
$twig->registerFunction('raw_html', fn()=>'<b>X</b>', true); // translates to ['is_safe'=>['html']]
```

### 3.2 Lifecycle States
| State | Description |
|-------|-------------|
| Pending | Registered before environment build; queued |
| Active  | Applied to current environment |
| Removed | Unregistered; triggers environment reset at next render to fully detach |

### 3.3 Unregistration Semantics
- Removing a function/filter invalidates only dynamic registrations.
- Facade nulls environment to ensure Twig reconstructs symbol table (cheap on next render).
- Idempotent: double removal returns false.

## 4. TemplateInvalidator

Location: `Daycry\Twig\Invalidation\TemplateInvalidator`

Goal: Remove compiled cache files for one or more logical template names using Twig's naming convention (md5 hash of logical name plus extension) with minimal filesystem passes.

Key Methods:
- `invalidateOne(string $logicalName, string $cacheDir, bool $reinitialize, callable $resetTwig, callable $log): int`
- `invalidateMany(array $logicalNames, string $cacheDir, bool $reinitialize, callable $resetTwig, callable $log): array`
- `invalidateNamespace(?string $namespace, string $cacheDir, bool $reinitialize, FilesystemLoader $loader, callable $resetTwig, callable $log): array`

Return Shapes:
- `invalidateMany` returns: `['removed' => int, 'templates' => array<string,int>, 'reinit' => bool]`.

Optimization:
- For batches the cache directory is scanned once and matched against a hash map of requested logical names, drastically cutting I/O.

### 4.1 Complexity
| Operation | Complexity (File Backend) |
|-----------|---------------------------|
| Single | O(F) worst-case (iterator scan) |
| Batch N | O(F + N) with hash short-circuit |
| Namespace | O(F_ns) where F_ns = files under selected namespace paths |

CI backend (key-value) reduces all operations to O(N) deletes (no directory scan) since compiled keys are known via adapter index.

### 4.2 Side Effects
- Updates compile index (removes logical entries)
- Persists invalidation state (cumulative + last)
- Optional environment reset (`--reinit` flag or parameter)

### 4.3 Namespace Resolution
1. Discovery list (possibly restored) enumerated.
2. Filter list by prefix `@ns/` (or non-prefixed for root).
3. Delegate to batch invalidation.

### 4.4 Safety Considerations
Compiled filename strategy relies on md5 of logical filename + extension substring match. Probability of false positive extremely low for typical directory sizes; any collision would only over-delete extra compiled classes (safe fallback: recompilation).

## Lifecycle (Render / Warmup / Invalidate)

1. `render()` → Lazy-create Environment → apply static + queued dynamic functions/filters → render (compilation occurs if not already cached) → mark compiled.
2. `warmup([...])` → Ensure compile index loaded → `twig->load()` each logical template → mark newly compiled → persist index if changed.
3. `invalidateTemplate(s)/invalidateNamespace()` → Determine affected compiled files → delete → update `TemplateCacheManager` set → persist index if changed → optionally reinitialize environment.

### 5. Performance Instrumentation
`Twig::getDiagnostics()` exposes:
| Key | Meaning |
|-----|---------|
| `renders` | Total successful render calls this request lifecycle |
| `performance.total_render_time_ms` | Sum of measured render durations (wall clock) |
| `performance.avg_render_time_ms` | Average per render (total / renders) |
| `cache.compiled_templates` | Count of logical templates marked compiled (from index) |
| `discovery.hits/misses` | Discovery cache reuse vs scans |
| `discovery.cache_source` | `scan` / `persisted` / `persisted-preload` / `apcu` |
| `invalidations.cumulative_removed` | Total compiled files removed since start or persisted state |
| `warmup.summary` | Last warmup compile/skip/error counts |

Interpretation Tips:
- High `misses` with stable template tree → confirm you're not in lean mode without snapshot override (`enableDiscoverySnapshot`).
- Large gap between `compiled_templates` and template list size → run warmup or investigate missing paths.
- Rising avg render time → inspect custom filters/functions or enable opcode cache if disabled.

### 5.1 Measuring Additional Metrics
Hook into render pipeline by wrapping `Twig::render()` or by post-processing diagnostics; avoid internal service mutation. For deep profiling use Xdebug or Blackfire.

## Combined Examples

### Selective Warmup With Status Listing
```php
$twig = new \Daycry\Twig\Twig();
$twig->warmup(['welcome','emails/layout']);
$all = $twig->listTemplates(true); // ['name'=>..., 'compiled'=>bool]
```

### Namespace Invalidation
```php
$result = $twig->invalidateNamespace('@admin', true);
// $result['removed'] => total files removed
```

### Late Dynamic Registration
```php
$twig->registerFunction('now_iso', fn()=> date(DATE_ATOM));
echo $twig->render('dashboard', []);
```

## Extending / Overriding

To customize behavior of a service:
1. Subclass (e.g. `App\Twig\MyDiscovery extends TemplateDiscovery`).
2. In a service provider/bootstrap, create your `Twig` instance and (if exposing setters in future) swap the internal service or wrap the facade at composition root.

## FAQ

**Do I need to interact with these services directly?**  No for normal usage. They exist for advanced extensions, profiling, or alternative caching strategies.

**Why a major version if the public API did not break?**  Internal restructuring is significant; semantic versioning communicates the risk for consumers relying on internal details.

**Can I manually clear the compile index?**  Yes: delete `compile-index.json` and compiled cache files; they are regenerated on next warmup/render.

**How do I know if a template is compiled?**  Call `listTemplates(true)` and check the `compiled` flag.

## Summary

The modular services isolate responsibilities, improve testability, and open the door to future enhancements (dependency graphs, distributed cache, metrics). Keep application code using the `Twig` facade unless you truly need lower-level hooks.

---
Brief: v3.0.0 introduces modular internal services (Discovery, CacheManager, DynamicRegistry, Invalidator) for a slimmer facade and faster warmup/invalidation while preserving the public API.
