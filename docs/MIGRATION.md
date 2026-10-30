# Migrating from 3.x to 4.0

Version 4.0 introduces a standalone core and a separately installable CodeIgniter
4 adapter. Both are developed in this repository; the adapter source is in
`packages/codeigniter4`. Framework classes move namespaces, so this is a major
release. PHP remains **8.2+**; Twig **3.30+** provides native removable cache behavior.

For installation examples and a local path setup, see
[INSTALLATION.md](INSTALLATION.md).

## Standalone applications

Install `daycry/twig:^4.0`. Construct `Daycry\Twig\Configuration` and pass it to
`Daycry\Twig\Twig`. Provide template directories explicitly. Compiled caching is
disabled by default. Configuration, persistence and logging do not resolve
framework services or depend on `APPPATH`, `WRITEPATH` or `ENVIRONMENT`.

## CodeIgniter applications

Install `daycry/twig-codeigniter4:^4.0`. It requires `daycry/twig:^4.0` and
`codeigniter4/framework:^4.7`. Both packages are maintained in this source repository;
the adapter is exported to its distribution repository when publishing.

Update imports and application configuration:

| Previous class | 4.0 class |
|---|---|
| `Daycry\Twig\Twig` | `Daycry\Twig\CodeIgniter4\Twig` for framework integration |
| `Daycry\Twig\Config\Twig` | `Daycry\Twig\CodeIgniter4\Config\Twig` |
| `Daycry\Twig\Config\Services` | `Daycry\Twig\CodeIgniter4\Config\Services` |
| `Daycry\Twig\Traits\Viewable` | `Daycry\Twig\CodeIgniter4\Traits\Viewable` |
| `Daycry\Twig\Debug\Toolbar\Collectors\Twig` | `Daycry\Twig\CodeIgniter4\Debug\Toolbar\Collectors\Twig` |
| `Daycry\Twig\Commands\*` | `Daycry\Twig\CodeIgniter4\Commands\*` |
| `Daycry\Twig\Cache\CICacheAdapter` | `Daycry\Twig\CodeIgniter4\Cache\CICacheAdapter` |

`service('twig')`, helper function names and Spark command names remain available
through adapter discovery. `php spark twig:publish` generates a minimal config
extending the adapter. Merge existing application settings before replacing a
previously published config.

## Changed cache and render behavior

- Invalidation deletes the artifact identified by Twig instead of guessing an MD5
  filename. It also refreshes the current environment's loaded template.
- `warmupAll()` initializes its loader on a fresh instance. Warmup writes compiled
  source directly to the configured cache, without executing templates.
- Warmup verifies real artifacts and source freshness, including changes within
  filesystem timestamp resolution. Missing files are compiled again.
- `saveData = true` now actually makes previous parameters available to subsequent
  renders. Use `saveData = false` for independent contexts.
- Discovery checks nested names. For a deployment-controlled immutable tree,
  `discoveryVersion` permits reuse without scanning; update it on each deployment.
- Remote compiled caches use generation invalidation. If `cacheTtl` is zero,
  compiled entries expire after 86400 seconds to reclaim obsolete generations;
  recompilation is automatic. Metadata and the current generation retain TTL zero.
- Whole-cache clearing cannot report the exact number of remote objects removed;
  its return value is zero for generation invalidation.
- Warmup notifications are emitted once per operation, including full warmup.
- Filesystem clearing maintains a `.twig-generation` marker for PHP workers.
  Diagnostic resets preserve it and work through the configured backend.
- After unregistering a function/filter, a template still using it is recompiled
  and raises the normal Twig `SyntaxError` for an unknown symbol, rather than an
  error from stale compiled code.

Run the previous installation's Twig cache-clear command before switching packages
during deployment. This avoids
retaining legacy index keys or remote payloads. Treat cache storage as dedicated
to Twig; do not place application data inside its configured cache directory.

The versioned installation examples apply after both 4.0 packages are published.
The core keeps its existing Packagist entry; the adapter has a separate entry.
See [RELEASING.md](RELEASING.md) for the distribution and registration procedure.
