[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

# Twig, the flexible, fast, and secure template language for Codeigniter 4

Twig is a template language for PHP.

Twig uses a syntax similar to the Django and Jinja template languages which inspired the Twig runtime environment.

[![PHP Tests](https://github.com/daycry/twig/actions/workflows/phpunit.yml/badge.svg?branch=master)](https://github.com/daycry/twig/actions/workflows/phpunit.yml)
[![PHPStan](https://github.com/daycry/twig/actions/workflows/phpstan.yml/badge.svg?branch=master)](https://github.com/daycry/twig/actions/workflows/phpstan.yml)
[![PHPCSFixer](https://github.com/daycry/twig/actions/workflows/phpcsfixer.yml/badge.svg?branch=master)](https://github.com/daycry/twig/actions/workflows/phpcsfixer.yml)
[![Rector](https://github.com/daycry/twig/actions/workflows/rector.yml/badge.svg?branch=master)](https://github.com/daycry/twig/actions/workflows/rector.yml)
[![PHPCPD](https://github.com/daycry/twig/actions/workflows/phpcpd.yml/badge.svg?branch=master)](https://github.com/daycry/twig/actions/workflows/phpcpd.yml)
[![Deptrac](https://github.com/daycry/twig/actions/workflows/deptrac.yml/badge.svg?branch=master)](https://github.com/daycry/twig/actions/workflows/deptrac.yml)
[![Coverage Status](https://coveralls.io/repos/github/daycry/twig/badge.svg?branch=master)](https://coveralls.io/github/daycry/twig?branch=master)

[![PHP Version Require](https://img.shields.io/packagist/dependency-v/daycry/twig/php?color=blue)](https://packagist.org/packages/daycry/twig)
[![Latest Stable Version](https://img.shields.io/github/v/release/daycry/twig?label=stable)](https://packagist.org/packages/daycry/twig)
[![Total Downloads](https://img.shields.io/packagist/dt/daycry/twig)](https://packagist.org/packages/daycry/twig)
[![Monthly Downloads](https://img.shields.io/packagist/dm/daycry/twig)](https://packagist.org/packages/daycry/twig)
[![GitHub stars](https://img.shields.io/github/stars/daycry/twig?style=social)](https://github.com/daycry/twig/stargazers)
[![License](https://img.shields.io/github/license/daycry/twig)](https://github.com/daycry/twig/blob/master/LICENSE)

## Requirements

* **PHP ≥ 8.2** (uses enums, readonly properties, intersection types).
* **CodeIgniter 4.7+** (`codeigniter4/framework: ^4.7`).
* **Twig 3.x** (`twig/twig: ^3.1.1`).

The CI matrix runs on PHP 8.2, 8.3, 8.4 and 8.5.

## What's new (Unreleased)

Audit follow-ups on top of v3.x — backwards-compatible:

* **Security**
    * Compiled templates served through the CI cache backend are now signed
      with HMAC-SHA256 and verified before `eval()`. A compromised cache can
      no longer feed arbitrary PHP into the host process.
    * Public APIs that touch the filesystem (`addPath`, `invalidateTemplate*`,
      `invalidateNamespace`, matching CLI commands) reject path traversal,
      null bytes and out-of-charset names through `TemplateNameValidator`.
    * Persisted JSON (compile index, warmup summary, invalidations,
      discovery snapshot) is decoded through `PersistenceDecoder` with type
      validation; tampered or wrong-shape payloads are dropped instead of
      crashing.
* **Operational tooling**
    * `php spark twig:lint` validates Twig syntax without rendering — safe in
      CI/CD pipelines, returns non-zero on the first error, supports `--json`.
    * `php spark twig:doctor` runs a health check (paths, cache writability,
      APCu, reconstructed indexes, Twig version) and exits non-zero on errors.
    * Per-template render profiler in `getDiagnostics()['performance']`
      exposes `per_template` and `top_templates` (count / total / avg / max ms).
    * Optional **PSR-3 logger** injection via `new Twig($config, $logger)`
      or `$twig->setLogger($logger)`. Falls back to `log_message()` when unset.
    * All CLI commands now return integer exit codes (`EXIT_SUCCESS`,
      `EXIT_USER_INPUT`, `EXIT_ERROR`).
    * New helpers: `twig_render()`, `twig_display()`, `twig_capture()`.
* **Internal**
    * `Daycry\Twig\Contracts\` — service interfaces (Discovery, CacheManager,
      Invalidator, DynamicRegistry) for mocking and alternative
      implementations.
    * `Daycry\Twig\Constants\` — `TwigEvent`, `TwigCacheKey`, `CacheSource`
      enums replacing magic strings.
    * `Daycry\Twig\Config\CapabilitiesProfile` — readonly value object that
      resolves the lean/full matrix (lifted out of the facade).
    * `AbstractTwigCommand` consolidates the shared scaffolding of the 11
      CLI commands.

See `CHANGELOG.md` for the full diff and `docs/TROUBLESHOOTING.md` for
common-case investigations.

## v3.0.0 Highlights

Major architectural refactor (internal) with backward‑compatible public API:

* Extracted modular services:
    * TemplateDiscovery – template enumeration & in-process caching
    * TemplateCacheManager – compile index (compile-index.json) & compiled state tracking
    * DynamicRegistry – runtime registration of functions & filters
    * TemplateInvalidator – single/batch/namespace cache invalidation
* New listing filters: namespace + glob/pattern (e.g. `@admin/*`, `emails/user_*`).
* Optimized batch invalidation (single directory scan for multiple templates).
* Warmup now persists & reuses a compile index; skipping already compiled templates is faster.
* Structured logging normalized: `event=twig.* key=value` pairs (update log parsers if any).
* Namespace-specific autoescape strategy mapping (`setAutoescapeForNamespace`).
* `Twig` facade slimmed; internal arrays removed in favor of service classes.

Service Architecture Guide: See `docs/SERVICES.md` for an in-depth explanation of the new modular internal services (Discovery, CacheManager, DynamicRegistry, Invalidator) and advanced usage patterns.

Upgrade Notes:

* No breaking changes for typical usage (render/display/registerFunction/registerFilter/warmup/invalidate APIs preserved).
* If you accessed internal properties like `$compiledTemplates` directly, migrate to the public methods (`warmup`, `listTemplates`, `invalidate*`).
* Log format changed; adjust any monitoring tools expecting old message text.
* Version bumped to v3 to reflect the internal restructuring & new operational capabilities rather than surface API breaks.

### Current Behavior
Discovery snapshot, preload and APCu acceleration are automatic in the default profile (`leanMode = false`). In Lean Mode they are disabled unless explicitly re-enabled with `enableDiscoverySnapshot`.

### v3.x Runtime Capability Model

Runtime features are governed by a profile (Full vs Lean) plus nullable overrides:

| Capability | Full (leanMode = false) | Lean (leanMode = true) | Override `null` | Override `true` | Override `false` |
|------------|-------------------------|-------------------------|-----------------|-----------------|------------------|
| Discovery Snapshot (persist + preload + APCu) | ON | OFF | Inherit profile | Force ON | Force OFF |
| Warmup Summary Persistence | ON | OFF | Inherit profile | Force ON | Force OFF |
| Invalidation History (last + cumulative) | ON | OFF | Inherit profile | Force ON | Force OFF |
| Dynamic Metrics (function/filter counts) | ON | OFF | Inherit profile | Force ON | Force OFF |
| Extended Diagnostics (names lists, static counts) | ON | OFF | Inherit profile | Force ON | Force OFF |

`null` means "inherit from the profile". Setting an explicit `true` /
`false` always wins over the Full/Lean default.

### Automatic Cache Backend Detection

The library always calls `service('cache')`:
* If the handler is a `FileHandler` (class name contains `File`) ⇒ `filesystem` mode using `cachePath` (default `WRITEPATH/cache/twig`).
* Any other handler ⇒ `service` mode (wrapped in `CICacheAdapter` for compiled templates and indexes; payloads are HMAC-signed before `eval()`).

Diagnostics (`getDiagnostics()['cache']`):
```jsonc
{
    "enabled": true,
    "path": "/var/www/app/writable/cache/twig",   // null when mode=service
    "mode": "filesystem" | "service",
    "service_class": "CodeIgniter\\Cache\\Handlers\\RedisHandler", // service mode only
    "prefix": "twig_",    // derived from Config\Cache::$prefix
    "ttl": 0,              // 0 = no expiry (typical for compiled artifacts)
    "compiled_templates": 42,
    "reconstructed_index": false
}
```

#### Cache Key Prefix (Simplified)

The prefix strategy was further simplified: we no longer embed the textual global cache prefix into Twig keys. Instead only two possibilities exist:

* Global `Config\Cache::$prefix` ends with `_` (non-empty) ⇒ prefix used: `_twig_`
* Otherwise ⇒ prefix used: `twig_`

The previous form `(<global> + '_') . 'twig_'` was removed to avoid accidental duplication and shorten keys. The removed per-Twig `cachePrefix` override remains removed.

Examples:
* `''` → `twig_`
* `'app'` → `twig_`
* `'app_'` → `_twig_`
* `'mediaprous_'` → `_twig_`

Applies uniformly to compiled templates, compile index, discovery snapshot, warmup summary & invalidation history.

Diagnostics expose the resolved value at `diagnostics['cache']['prefix']`.

### Persistence Medium Map (`diagnostics['persistence']`)
Possible keys: `compile_index`, `discovery_snapshot`, `warmup`,
`invalidations`.

Value: `{ "medium": "file" | "ci" }` where `ci` indicates the cache service
backend (any non-`File` handler). Example:
```json
"persistence": {
    "compile_index": { "medium": "ci" },
    "discovery_snapshot": { "medium": "ci" },
    "warmup": { "medium": "ci" },
    "invalidations": { "medium": "ci" }
}
```

### Reconstructed Index
If the compile index (`compile-index.json` or remote key) loads empty but
compiled PHP files are detected on disk (upgrade or manual copy), a
synthetic index is built with `unknown_N` names. The
`reconstructed_index = true` flag warns of this state — run a warmup to
regenerate a real index.

### Lean vs Full Diagnostics Output
Lean Mode drops entire sections (the keys disappear) to keep the payload
small. A lean instance with no overrides only exposes: `renders`,
`last_render_view`, `environment_resets`, `cache`, `performance`,
`capabilities`, `persistence` (plus `discovery` when forced). Setting any
override to `true` re-introduces just that section.

### Debug Toolbar Tuning

For large installs or pages with heavy JavaScript, the Twig toolbar panel
can add latency if it renders every section (discovery, dynamics, templates)
on each request. The flags below trim the rendered output directly — there
is no longer a deferred / async-fetch mode (it was removed to avoid route
recursion).

Config flags (on `Config\Twig`):

| Flag | Default | Effect |
|------|---------|--------|
| `toolbarMinimal` | `false` | When `true` only Core + Cache + Performance; skips Discovery, Warmup, Invalidations, Dynamics, Templates, Capabilities, Persistence. |
| `toolbarShowTemplates` | `true` | Show / hide the templates table. Ignored when `toolbarMinimal=true`. |
| `toolbarMaxTemplates` | `50` | Hard cap on rows in the templates table. |
| `toolbarShowCapabilities` | `true` | Show the capabilities section. Ignored when minimal. |
| `toolbarShowPersistence` | `true` | Show the persistence-medium section. Ignored when minimal. |

Maximum-performance dev view:
```php
$config->toolbarMinimal = true; // only essential metrics
```

Intermediate profile without the templates table but keeping capabilities
and persistence:
```php
$config->toolbarMinimal = false;
$config->toolbarShowTemplates = false;
```

Suggested strategy:
1. Start with `toolbarMinimal=true` if you only debug counts and cache.
2. Add sections one at a time: turn minimal off, then disable only what
   you don't need (`toolbarShowTemplates=false`, lower `toolbarMaxTemplates`).
3. Use Lean Mode to also shrink the JSON structure when consuming
   diagnostics externally.

Notes:
- All sections render inline; there are no secondary requests.
- No JavaScript dependency for loading dynamic panel content.
- The optimisations target avoiding unnecessary HTML construction.


### Quick Examples
Force discovery snapshot in Lean Mode:
```php
$config->leanMode = true;
$config->enableDiscoverySnapshot = true; // snapshot only; warmupSummary, invalidations, metrics stay OFF
```

List templates with compiled status:
```php
$twig->listTemplates(true); // [['name'=>'welcome','compiled'=>true], ...]
```

Warm up and inspect the summary:
```php
$twig->warmup(['welcome']);
print_r($twig->getDiagnostics()['warmup']);
```



## Installation via composer

Use the package with composer install

	> composer require daycry/twig


## Configuration

Run command:

	> php spark twig:publish

This command will copy a config file to your app namespace.
Then you can adjust it to your needs. By default file will be present in `app/Config/Twig.php`.

### Configuration Quick Start
```php
$config = new \Daycry\Twig\Config\Twig();

// Optional: enable strict variables (throw on undefined)
$config->strictVariables = true;

// Optional: Lean Mode (disable non-essential persistence & diagnostics)
$config->leanMode = true;                      // minimal overhead
$config->enableDiscoverySnapshot = true;       // re-enable snapshot in lean if you have many templates

// Custom template paths (optionally with namespace)
$config->paths = [APPPATH.'Module/Views', [APPPATH.'Admin/Views','admin']];

// Create instance
$twig = new \Daycry\Twig\Twig($config);
```

Profiles Summary:
- Full (default): snapshot + preload + APCu (if available) + all diagnostics.
- Lean: minimal persistence; selectively re-add capabilities via nullable overrides.


## Usage Loading Library

```php
$twig = new \Daycry\Twig\Twig();
$twig->display( 'file.html', [] );

```

## Usage as a Service

```php
$twig = \Config\Services::twig();
$twig->display( 'file.html', [] );

```

## Usage as a Helper

In your BaseController - `$helpers` array, add an element with your helper filename.

```php
protected $helpers = [ 'twig_helper' ];
```

The helper provides a few convenience wrappers around the shared service:

```php
// Same instance as Services::twig()
$twig = twig_instance();
$twig->display('file.html', []);

// Render-and-return / render-and-echo without resolving the service by hand
$html = twig_render('emails/welcome', ['name' => 'Daycry']);
twig_display('layout/main', ['title' => 'Home']);

// Capture stdout from a callable as a string
$captured = twig_capture(static fn () => twig_display('partials/sidebar'));
```

## Add Globals

```php
$twig = new \Daycry\Twig\Twig();

$session = \Config\Services::session();
$session->set( array( 'name' => 'Daycry' ) );
$twig->addGlobal( 'session', $session );
$twig->display( 'file.html', [] );

```

## File Example

```php

<!DOCTYPE html>
<html lang="es">  
    <head>    
        <title>Example</title>    
        <meta charset="UTF-8">
        <meta name="title" content="Example">
        <meta name="description" content="Example">   
    </head>  
    <body>
        <h1>Hi {{ name }}</h1>
        {{ dump( session.get( 'name' ) ) }}
    </body>  
</html>

```

## Collector

If you want to debug the data in twig templates.

Toolbar.php file
```php

    use Daycry\Twig\Debug\Toolbar\Collectors\Twig;
    
    public array $collectors = [
        ...
        //Views::class,
        Twig::class
    ];

```

## Advanced Features

### Caching & Persistence Overview

This integration implements a multi-layer caching architecture covering:
1. Compiled template classes (auto-detected backend: CI cache service if available, otherwise filesystem)
2. Compile index (logical template -> compiled flag)
3. Template discovery stats + optional snapshot (with fingerprint & APCu acceleration)
4. Warmup summary persistence
5. Invalidation state (last + cumulative)

Backend selection is automatic (CI cache service if available, otherwise filesystem). Prefix derives from global cache config; TTL normally unlimited.

Discovery snapshot, preload and APCu acceleration are now automatic in the full profile (leanMode = false). Use `enableDiscoverySnapshot` when in Lean Mode to opt back in.

Warm all templates once after deployment:
```
php spark twig:warmup --all
```

Clear everything (compiled + persisted artifacts):
```
php spark twig:clear-cache --reinit
```

See full details, key layout, and troubleshooting in `docs/CACHING.md`.

### Further Reading
- `CHANGELOG.md` — release-by-release diff (Keep-a-Changelog format).
- `CONTRIBUTING.md` — local quality gates and contribution conventions.
- `docs/SERVICES.md` — modular internal services (Discovery, CacheManager, DynamicRegistry, Invalidator) plus contracts, profiler, logger bridge, validators.
- `docs/PERFORMANCE.md` — warmup strategy, discovery tuning, batch optimisation, render profiler.
- `docs/CACHING.md` — multi-layer caching architecture, key layout, lean-mode matrix.
- `docs/DIAGNOSTICS_REFERENCE.md` — full schema for every key in `getDiagnostics()`.
- `docs/TROUBLESHOOTING.md` — common-case investigations (no templates discovered, cache never cleared, HMAC drops, APCu warnings, exit-code-zero-on-failure, etc.).

### Lean Mode (Low-Overhead Profile)

Enable Lean Mode to minimize persistence & diagnostic overhead:
```php
$config->leanMode = true; // disables warmup summary, invalidation history, discovery snapshot, dynamic & extended diagnostics
```
Re-enable selected capabilities while staying in Lean:
```php
$config->leanMode = true;
$config->enableDiscoverySnapshot = true;   // keep snapshot for faster discovery
$config->enableWarmupSummary = true;       // record last warmup result
```
If `leanMode = false` (default) all capabilities are active automatically (snapshot always on now).

See `docs/CACHING.md` (section "Lean Mode & Capability Overrides") and `docs/PERFORMANCE.md` for rationale & cost matrix.

### Custom Loader Injection

Replace the internal loader (e.g. use an in-memory `ArrayLoader` for tests):

```php
use Twig\\Loader\\ArrayLoader;
use Daycry\\Twig\\Twig;

$twig = new Twig();
$twig->withLoader(new ArrayLoader([
    'hello.twig' => 'Hello {{ name }}'
]));

echo $twig->render('hello', ['name' => 'World']);
```

### Strict Variables

Enable strict mode (undefined variables throw a `RuntimeError`):

```php
$config = new \\Daycry\\Twig\\Config\\Twig();
$config->strictVariables = true;
$twig = new Twig($config);
```

### Dynamic Registration (Functions & Filters)

Register functions or filters at runtime, even before the first render. Items queued before initialization are applied automatically.

Supports:
1. Boolean shorthand (backward compatible) → safe HTML when true.
2. Array options mirroring native Twig options (`is_safe`, `needs_environment`, `needs_context`, etc.).

```php
// Boolean shorthand
$twig->registerFunction('hello_fn', fn(string $n) => 'Hello ' . $n);             // escaped by default
$twig->registerFunction('raw_html', fn() => '<b>Bold</b>', true);                // mark as safe HTML

// Array options (new)
$twig->registerFunction('upper_env',
    function(\Twig\Environment $env, string $v) { return strtoupper($v); },
    ['needs_environment' => true]
);

// Filters
$twig->registerFilter('exclaim', fn(string $v) => $v . '!', ['is_safe' => ['html']]);
$twig->registerFilter('italic', fn(string $v) => '<i>'.$v.'</i>', []);           // will be escaped
```

Usage in templates:

```twig
{{ hello_fn('World') }} {# Hello World #}
{{ raw_html() }}        {# <b>Bold</b> (not escaped) #}
{{ 'wow'|exclaim }}     {# wow! #}
{{ 'x'|italic }}        {# &lt;i&gt;x&lt;/i&gt; because unsafe #}
```

### Cache Management

Specify a custom cache path via config:

```php
$config = new \\Daycry\\Twig\\Config\\Twig();
$config->cachePath = WRITEPATH.'cache'.DIRECTORY_SEPARATOR.'twig_custom';
$twig = new Twig($config);
```

Clear compiled templates (optionally reinitializing the environment):

```php
$removedFiles = $twig->clearCache();       // remove compiled files only
$removedFiles = $twig->clearCache(true);   // also reset Twig Environment
$cacheDir     = $twig->getCachePath();
```

### Example: Combining Everything

```php
$config = new \\Daycry\\Twig\\Config\\Twig();
$config->strictVariables = true;
$config->cachePath = WRITEPATH.'cache/twig_app';

$twig = new Twig($config);

$twig->registerFunction('link', fn(string $t, string $u) => '<a href="'.esc($u,'url').'">'.esc($t).'</a>', true);
$twig->registerFilter('reverse', fn(string $v) => strrev($v));

echo $twig->render('page', ['title' => 'My Page']);
```

If you change templates programmatically and need a fresh compile, call `clearCache(true)`.

### Dynamic Extension Registration

You can add Twig extensions at runtime:

```php
use Daycry\Twig\Twig;
use App\Twig\MyExtension; // extends \Twig\Extension\AbstractExtension

$twig = new Twig();
$twig->registerExtension(MyExtension::class); // queued or immediate
```

### CLI: Clear Twig Cache

After installing, you can clear compiled templates from the CLI:

```
php spark twig:clear-cache
php spark twig:clear-cache --reinit   # also recreates the Environment
```

### Selective Template Invalidation

Remove cache for a single logical template name (without extension):

```php
$twig->invalidateTemplate('emails/welcome');           // best-effort removal
$twig->invalidateTemplate('emails/welcome', true);     // remove + reinitialize environment
```

CLI variant:

```
php spark twig:invalidate emails/welcome
php spark twig:invalidate emails/welcome --reinit
```

### Batch & Namespace Invalidation

Invalidate multiple logical template names in one call:

```php
$summary = $twig->invalidateTemplates(['welcome','emails/welcome','admin/dashboard']);
/* $summary example:
[
    'removed'   => 3,        // total cache files removed
    'templates' => [         // per logical template details
        'welcome' => 1,
        'emails/welcome' => 1,
        'admin/dashboard' => 1,
    ],
    'reinit' => false,
]*/

// Force environment recreation after invalidation:
$twig->invalidateTemplates(['welcome'], true);
```

Invalidate by namespace (when using namespaced paths `@namespace`):

```php
// Invalidate all templates under namespace '@admin'
$twig->invalidateNamespace('@admin');

// Invalidate all templates in the main (root) namespace
$twig->invalidateNamespace(null);
```

### Warmup / Precompilation

Precompile templates to avoid first-hit latency. Two APIs:

```php
// Specific templates (logical names without extension)
$summary = $twig->warmup(['welcome','emails/welcome']);
// All discovered templates under configured paths
$summaryAll = $twig->warmupAll();

/* Returned structure:
[
    'compiled' => 5,
    'skipped'  => 12, // already compiled
    'errors'   => 0,
]
*/

// Force recompilation ignoring existing compiled cache fingerprints
$twig->warmup(['welcome'], true);
```

CLI command:
```
php spark twig:warmup --all
php spark twig:warmup welcome emails/welcome
php spark twig:warmup welcome --force   # recompile even if cached
```

Warmup uses a heuristic hash check (md5 of logical name) to decide if a template seems compiled; `--force` bypasses this.

### Logging

By default the library writes structured `event=twig.* key=value ...` entries
through CodeIgniter's `log_message()` helper. No additional configuration is
required; verbosity is controlled by `app/Config/Logger.php`.

If you need monolog/syslog/etc., inject a PSR-3 logger via the constructor or
`setLogger()`:

```php
use Psr\Log\LoggerInterface;

/** @var LoggerInterface $logger */
$twig = new \Daycry\Twig\Twig($config, $logger);
// or later:
$twig->setLogger($logger);
$twig->setLogger(null); // restore the log_message() fallback
```

Representative events (levels vary: debug/info/error):
- `twig.function.queued`, `twig.function.registered`, `twig.function.unregistered`
- `twig.filter.queued`, `twig.filter.registered`, `twig.filter.unregistered`
- `twig.extension.queued`, `twig.extension.registered`, `twig.extension.unregistered`
- `twig.cache.enabled`, `twig.cache.disabled`, `twig.cache.cleared`
- `twig.cache.adapter.signature_invalid` (HMAC verification failed — entry dropped)
- `twig.template.invalidated`, `twig.templates.invalidated`, `twig.namespace.invalidated`
- `twig.warmup.compiled`, `twig.warmup.error`
- `twig.loader.replaced`, `twig.reset`, `twig.path.added`
- `twig.discovery.*` (snapshot persistence / migration / APCu)
- `twig.invalidations.*` (state load/save errors)
- `twig.warmup.summary.*` (state load/save errors)

Persistence catches that previously swallowed exceptions silently now log at
debug level (`event=twig.<area>.error msg=...`).

Public event identifiers are also available as a string-backed enum for
typed call sites:

```php
use Daycry\Twig\Constants\TwigEvent;

event(TwigEvent::WarmupAfter->value, $payload);
```

Breaking change vs ≤ 0.2.x: ad-hoc message formats were replaced by the
`event=...` shape; update any log parser that grepped for the old text.

### CLI Commands Overview

Every command extends `AbstractTwigCommand` and returns proper integer exit
codes (`EXIT_SUCCESS`, `EXIT_USER_INPUT`, `EXIT_ERROR`) so failures actually
fail in CI/CD pipelines.

| Command | Description | Common Options / Notes |
|---------|-------------|------------------------|
| `php spark twig:publish` | Publish the config file to `app/Config/Twig.php`. | Run once after install. |
| `php spark twig:clear-cache` | Delete compiled cache files. | `--reinit` recreate environment after clearing. |
| `php spark twig:invalidate <template>` | Invalidate a single logical template. | `--reinit` if removed. Name without extension. Validated for path traversal. |
| `php spark twig:invalidate:batch <t1> <t2> ...` | Invalidate multiple logical templates. | `--reinit` if any removed. |
| `php spark twig:warmup` | Precompile specific templates. | Provide names or use `--all`; `--force` to ignore existing cache; `--json` and `--verbose` available. |
| `php spark twig:warmup:status` | Show last warmup summary (`warmup-summary.json`). | `--json`. |
| `php spark twig:list` | List discovered logical templates. | `--status` to include compiled flag, `--json`. |
| `php spark twig:stats` | Show counts & cache information. | Reads compile index and cache directory. |
| `php spark twig:lint [template]` | Validate Twig syntax without rendering. | If `template` is omitted, every discovered template is linted. `--json`. Non-zero exit on syntax errors. |
| `php spark twig:doctor` | Health check (paths, cache, APCu, index, version). | `--json`. Non-zero exit when any check is ERROR-level. |
| `php spark twig:diagnostics` | Print full `getDiagnostics()` output. | `--json`. |
| `php spark twig:reset-metrics` (alias `twig:reset`) | Reset diagnostic artifacts (discovery stats, warmup summary, optionally compile index/cache). | `--include-index`, `--include-cache`, `--json`. |

### Template Listing & Filtering

Use the `listTemplates()` API to enumerate logical names. You can filter by namespace and/or a glob-style pattern (`*` and `?`).

```php
// All templates
$all = $twig->listTemplates();

// All templates inside a namespace
$admin = $twig->listTemplates(false, '@admin');

// Pattern filtering (within namespace)
$adminDash = $twig->listTemplates(false, '@admin', 'dash*/index');

// With compiled status
$withStatus = $twig->listTemplates(true);
```

Patterns are case-insensitive. If a namespace is supplied, the pattern is matched against the path portion inside that namespace.

### Template Discovery Cache

Discovered logical template names are cached per-process (context hash: loader class + paths + extension). The cache invalidates automatically when:
1. Loader is replaced (`withLoader()`)
2. Environment reset (`resetTwig()`)
This reduces filesystem traversal on repeated listing or namespace invalidation operations.

## Changelog

Release-by-release notes live in [`CHANGELOG.md`](CHANGELOG.md). The
`[Unreleased]` section there mirrors the **What's new** block at the top of
this file.

## Common runtime knobs

A reference of small features that don't deserve a top-level section:

#### Unregister runtime additions

```php
$twig->unregisterFunction('temp_fn');
$twig->unregisterFilter('brackets');
$twig->unregisterExtension(MyExtension::class);
```

#### Runtime cache toggle (development convenience)

```php
$twig->disableCache();          // turns off cache (does not delete existing by default)
$twig->disableCache(true);      // also removes existing compiled files
$twig->enableCache();           // re-enable with default path
$twig->enableCache('/custom/path');
if (! $twig->isCacheEnabled()) { /* ... */ }
```

#### Namespace auto-escape mapping

Set different escaping strategies per Twig namespace (leading `@` omitted):

```php
$twig->setAutoescapeForNamespace('admin', 'html');
$twig->setAutoescapeForNamespace('rawmail', false);   // disable escaping
$twig->removeAutoescapeForNamespace('rawmail');
```

#### Persistent compile index & listing

Warmup operations store compiled logical names into `compile-index.json`
inside the cache directory:

```php
$twig->warmup(['welcome']);
$templates = $twig->listTemplates(true); // [['name' => 'welcome', 'compiled' => true], ...]
```

#### Render profiler

When `extendedDiagnostics` is on (default in the full profile), every render
records its wall-clock cost; the aggregate is exposed via diagnostics:

```php
$diag = $twig->getDiagnostics();
print_r($diag['performance']['per_template']);
print_r($diag['performance']['top_templates']); // top 10 by total ms
```

A bounded `__overflow__` bucket caps per-template entries to keep memory
predictable on long-lived workers.

