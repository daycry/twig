# Daycry Twig: standalone PHP templates with an optional CodeIgniter 4 adapter

Render Twig templates in standalone PHP applications or through the optional
CodeIgniter 4 adapter. The toolkit adds template discovery, cache warmup,
invalidation, diagnostics and PSR-3 logging. Requires PHP **8.2+** and Twig
**3.30+**; framework integration requires CodeIgniter **4.7+**.

Version **4.0** separates the standalone core from the CodeIgniter adapter.
Configuration and framework namespaces change; see
[the migration guide](docs/MIGRATION.md) before upgrading from 3.x.

Both packages are developed in this repository:

| Package | Purpose |
|---|---|
| `daycry/twig` | Framework-independent renderer, configuration, discovery, warmup and cache lifecycle |
| `daycry/twig-codeigniter4` | Optional CodeIgniter 4 services, helpers, Spark commands, cache adapter and toolbar |

The adapter source lives in `packages/codeigniter4`. Its distribution repository
is generated from that directory with `git subtree split`; development stays
here. See [the release procedure](docs/RELEASING.md).

## Installation

Install the package for your application:

```bash
# Standalone PHP
composer require daycry/twig:^4.0

# CodeIgniter 4 (also installs the core and framework dependency)
composer require daycry/twig-codeigniter4:^4.0
```

The core does not install CodeIgniter. See the [installation guide](docs/INSTALLATION.md)
for requirements, first templates and local development from the checkout.

## Standalone PHP

```php
require __DIR__ . '/vendor/autoload.php';

use Daycry\Twig\Configuration;
use Daycry\Twig\Twig;

$config = new Configuration();
$config->paths = [__DIR__ . '/templates'];
$config->cachePath = __DIR__ . '/var/cache/twig';
$config->strictVariables = true;

$twig = new Twig($config);
echo $twig->render('welcome', ['name' => 'Ada']);
```

Template names omit the configured extension (`.twig` by default). The standalone
configuration has no default search directories and disables compiled caching
until a cache directory is supplied. It does not define framework constants or
load global helpers. Native Twig loaders work through `withLoader()`:

```php
$twig->withLoader(new \Twig\Loader\ArrayLoader([
    'hello.twig' => 'Hello {{ name }}',
]));
echo $twig->render('hello', ['name' => 'Ada']);
```

`saveData` defaults to true: render parameters remain available to later renders
on the same renderer. Set it to false for independent render contexts. Keep a
renderer scoped to its request or job when stored context is enabled.

## CodeIgniter 4

Publish an application configuration:

```bash
php spark twig:publish
```

The generated `app/Config/Twig.php` extends
`Daycry\Twig\CodeIgniter4\Config\Twig`. Use the shared service:

```php
$twig = service('twig');
echo $twig->render('welcome', ['name' => 'Ada']);
```

To construct an independent adapter directly:

```php
$config = new \Daycry\Twig\CodeIgniter4\Config\Twig();
$config->paths = [APPPATH . 'Module/Views'];
$twig = new \Daycry\Twig\CodeIgniter4\Twig($config);
```

The adapter adds `APPPATH . 'Views'`, framework URL/form helpers, validation,
cache-service integration and optional debug toolbar instrumentation. Helpers
are available after `helper('twig_helper')`: `twig_instance()`, `twig_render()`,
`twig_display()` and `twig_capture()`.

Register `Daycry\Twig\CodeIgniter4\Debug\Toolbar\Collectors\Twig` in your toolbar
collectors to enable its panel. The rendering path skips toolbar service lookups
in production.

## Shared APIs

```php
$twig->addPath(__DIR__ . '/admin/templates', 'admin');
$twig->registerFunction('greeting', static fn () => 'Hello');
$twig->registerFilter('caps', strtoupper(...), []);
$twig->addGlobal('applicationName', 'Example');

$templates = $twig->listTemplates();
$summary = $twig->warmupAll();
$twig->invalidateTemplate('welcome');
$twig->invalidateNamespace('@admin');
$diagnostics = $twig->getDiagnostics();
```

Warmup compiles source into the actual configured Twig cache without rendering
it. A persisted boolean alone cannot cause a missing or expired artifact to be
skipped. Invalidation uses native Twig cache keys for both filesystem and remote
backends.

## Logging, profiles and persistence

Pass an optional PSR-3 logger as the second constructor argument. Standalone
instances without a logger are silent; the CI4 adapter resolves the framework
logger. The logger can be replaced with `setLogger()`.

`leanMode = true` disables optional discovery snapshots, warmup summaries,
invalidation history and extended diagnostics. Nullable `enable*` options can
re-enable individual capabilities. Persistent state is JSON; filesystem writes
use temporary files and atomic replacement. In-process discovery hits do not
rewrite snapshots.

The default discovery mode checks nested template names on each new renderer.
For immutable deployment directories, set `discoveryVersion` to a deployment ID
to reuse persisted/APCu lists without traversing the tree. Change that ID whenever
the template tree changes. See [performance](docs/PERFORMANCE.md).

## Spark commands

The adapter provides `twig:publish`, `twig:list`, `twig:lint`, `twig:doctor`,
`twig:diagnostics`, `twig:stats`, `twig:warmup`, `twig:warmup:status`, cache
invalidation/clear commands and metric/reset commands. Use `php spark list`
for the exact names and options. Rendering and maintenance APIs are also
available directly from the standalone renderer.

## Development

```bash
composer install
composer test                 # standalone and CI4 suites
composer analyze
composer cs
composer architecture
composer ci
```

CI runs PHP 8.2, 8.3, 8.4 and 8.5. Isolated consumer checks install the core without
CodeIgniter and verify the adapter's framework discovery. No packages are
published by these checks.

- [Documentation index](docs/README.md)
- [Installation and first templates](docs/INSTALLATION.md)
- [Migration](docs/MIGRATION.md)
- [Services and extension points](docs/SERVICES.md)
- [Cache behavior](docs/CACHING.md)
- [Performance](docs/PERFORMANCE.md)
- [Diagnostics](docs/DIAGNOSTICS_REFERENCE.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)
- [Publishing both packages](docs/RELEASING.md)
- [Changelog](CHANGELOG.md)

## License

[MIT](LICENSE.md).
