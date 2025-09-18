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

## Lifecycle (Render / Warmup / Invalidate)

1. `render()` → Lazy-create Environment → apply static + queued dynamic functions/filters → render (compilation occurs if not already cached) → mark compiled.
2. `warmup([...])` → Ensure compile index loaded → `twig->load()` each logical template → mark newly compiled → persist index if changed.
3. `invalidateTemplate(s)/invalidateNamespace()` → Determine affected compiled files → delete → update `TemplateCacheManager` set → persist index if changed → optionally reinitialize environment.

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
