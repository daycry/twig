[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

# Twig, the flexible, fast, and secure template language for Codeigniter 4

Twig is a template language for PHP.

Twig uses a syntax similar to the Django and Jinja template languages which inspired the Twig runtime environment.

[![Build Status](https://github.com/daycry/twig/actions/workflows/phpunit.yml/badge.svg?branch=master)](https://github.com/daycry/twig/actions/workflows/phpunit.yml)
[![Coverage Status](https://coveralls.io/repos/github/daycry/twig/badge.svg?branch=master)](https://coveralls.io/github/daycry/twig?branch=master)
[![Downloads](https://poser.pugx.org/daycry/twig/downloads)](https://packagist.org/packages/daycry/twig)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/daycry/twig)](https://packagist.org/packages/daycry/twig)
[![GitHub stars](https://img.shields.io/github/stars/daycry/twig)](https://packagist.org/packages/daycry/twig)
[![GitHub license](https://img.shields.io/github/license/daycry/twig)](https://github.com/daycry/twig/blob/master/LICENSE)

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


## Installation via composer

Use the package with composer install

	> composer require daycry/twig


## Configuration

Run command:

	> php spark twig:publish

This command will copy a config file to your app namespace.
Then you can adjust it to your needs. By default file will be present in `app/Config/Twig.php`.


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

In your BaseController - $helpers array, add an element with your helper filename.

```php
protected $helpers = [ 'twig_helper' ];

```

And then you can use the helper

```php

$twig = twig_instance();
$twig->display( 'file.html', [] );

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
1. Compiled template classes (filesystem or CI cache backend)
2. Compile index (logical template -> compiled flag)
3. Template discovery stats + optional snapshot (with fingerprint & APCu acceleration)
4. Warmup summary persistence
5. Invalidation state (last + cumulative)

You can switch the compiled template backend to Redis/Memcached/etc. via:
```php
$config->cacheBackend = 'ci';      // default 'file'
$config->cachePrefix  = null;      // derive from Config\Cache::$prefix + 'twig_'
$config->cacheTtl     = 0;         // no expiry
```

Discovery performance tuning:
```php
$config->discoveryPersistList           = true; // persist list snapshot
$config->discoveryPreload               = true; // preload snapshot each request
$config->discoveryUseAPCu               = true; // APCu cross-process reuse
$config->discoveryFingerprintMtimeDepth = 0;    // dir mtime depth
```

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
- `docs/SERVICES.md` – Modular internal services (Discovery, CacheManager, DynamicRegistry, Invalidator)
- `docs/PERFORMANCE.md` – Warmup strategy, discovery tuning, invalidation efficiency, diagnostics interpretation

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
// Boolean (legacy style)
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

All logging now delegates entirely to CodeIgniter's native `log_message()` helper. No internal logger instance or `setLogger()` method exists anymore.

Log entries follow a normalized `event=<name> key=value ...` pattern for easier machine parsing. Representative events (levels vary: debug/info/error):
- `twig.functions.start`, `twig.functions.ready`
- `twig.function.queued`, `twig.function.registered`, `twig.function.unregistered`
- `twig.filter.queued`, `twig.filter.registered`, `twig.filter.unregistered`
- `twig.extension.queued`, `twig.extension.registered`, `twig.extension.queued_recreate`, `twig.extension.unregistered`
- `twig.cache.enabled`, `twig.cache.disabled`, `twig.cache.cleared`
- `twig.template.invalidated`, `twig.templates.invalidated`, `twig.namespace.invalidated`
- `twig.warmup.compiled`, `twig.warmup.error`
- `twig.loader.replaced`, `twig.reset`

Control verbosity via your global `app/Config/Logger.php`. No additional configuration is required inside `Twig`.

Breaking change: previous ad-hoc message formats were replaced; if you had log parsers, update them to consume the new key=value style.

### CLI Commands Overview

| Command | Description | Common Options / Notes |
|---------|-------------|------------------------|
| `php spark twig:publish` | Publish the config file to `app/Config/Twig.php`. | Run once after install. |
| `php spark twig:clear-cache` | Delete compiled cache files. | `--reinit` recreate environment after clearing. |
| `php spark twig:invalidate <template>` | Invalidate a single logical template. | `--reinit` if removed. Name without extension. |
| `php spark twig:invalidate:batch <t1> <t2> ...` | Invalidate multiple logical templates. | `--reinit` if any removed. |
| `php spark twig:warmup` | Precompile specific templates. | Provide names or use `--all`; `--force` to ignore existing cache. |
| `php spark twig:list` | List discovered logical templates. | `--status` to include compiled flag. |
| `php spark twig:stats` | Show counts & cache information. | Reads compile index and cache directory. |

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

## Changelog (recent additions)

Summary of notable enhancements (proposed release 0.3.0):
- Loader replacement via `withLoader()`
- Strict variables mode (`$strictVariables` config)
- Dynamic runtime registration & unregistration: `registerFunction()`, `unregisterFunction()`, `registerFilter()`, `unregisterFilter()`, `registerExtension()`, `unregisterExtension()`
- Cache utilities: `getCachePath()`, `clearCache()`, runtime toggles `disableCache()`, `enableCache()`, `isCacheEnabled()`
- Selective, batch, and namespace invalidation: `invalidateTemplate()`, `invalidateTemplates()`, `invalidateNamespace()` + CLI counterparts
- Warmup & precompilation: `warmup()`, `warmupAll()` with persisted compile index (`compile-index.json`)
- Listing & status: `listTemplates()` (with namespace/pattern filtering & compiled status)
- Namespace auto-escape strategy mapping: `setAutoescapeForNamespace()`, `removeAutoescapeForNamespace()`
- New CLI commands: `twig:invalidate`, `twig:invalidate:batch`, `twig:warmup`, `twig:list`, `twig:stats`, `twig:clear-cache`, `twig:publish`
- Unified structured logging (key=value) replacing legacy formats
- In-process discovery cache for logical template enumeration

## Roadmap / Ideas

Potential future exploration:
- Template dependency graph (track includes/extends for smarter invalidation)
- Optional metrics hook (expose render times to an external profiler)
- Pluggable cache backend abstraction (filesystem vs memory)
- Enhanced auto-escape strategies (contextual by file pattern)
- Interactive CLI (prompt-driven) for template operations

### New Features Documentation

#### Unregister Runtime Additions

Remove previously registered dynamic artifacts:

```php
$twig->unregisterFunction('temp_fn');
$twig->unregisterFilter('brackets');
$twig->unregisterExtension(MyExtension::class);
```

#### Runtime Cache Toggle

Disable recompilation writing and force non-cached rendering (development convenience):

```php
$twig->disableCache();          // turns off cache (does not delete existing by default)
$twig->disableCache(true);      // also removes existing compiled files
$twig->enableCache();           // re-enable with default path
$twig->enableCache('/custom/path');
if (!$twig->isCacheEnabled()) { /* ... */ }
```

#### Namespace Auto-Escape Mapping

Set different escaping strategies per Twig namespace (leading `@` omitted when specifying):

```php
$twig->setAutoescapeForNamespace('admin', 'html');
$twig->setAutoescapeForNamespace('rawmail', false);   // disable escaping
$twig->removeAutoescapeForNamespace('rawmail');
```

#### Persistent Compile Index & Listing

Warmup operations store compiled logical names into `compile-index.json` within the cache directory.

```php
$twig->warmup(['welcome']);
$templates = $twig->listTemplates(true); // [['name' => 'welcome', 'compiled' => true], ...]
```

#### New CLI Commands

```
php spark twig:list             # list logical template names
php spark twig:list --status    # include compiled status (from index)
php spark twig:stats            # show counts & cache info
php spark twig:invalidate:batch welcome emails/welcome
```

