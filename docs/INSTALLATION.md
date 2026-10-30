# Installation and first templates

## Choose the package

| Application | Composer package | Requirements |
|---|---|---|
| Standalone PHP | `daycry/twig` | PHP 8.2+, Twig 3.30+ |
| CodeIgniter 4 | `daycry/twig-codeigniter4` | PHP 8.2+, CodeIgniter 4.7+, core 4.0 |

The adapter requires the core and framework, so Composer installs them together.
The core does not require or load CodeIgniter. Both packages are developed here;
the adapter source is in `packages/codeigniter4` and its distribution is exported
when publishing. See [RELEASING.md](RELEASING.md) for maintainers' instructions.

The current source targets **4.0**, which is unreleased. Use the tagged commands
below after both packages have been published, or the local path setup at the
end of this guide while developing against the checkout.

## Standalone PHP

```bash
composer require daycry/twig:^4.0
```

Create `templates/welcome.twig`:

```twig
Hello {{ name }}!
```

Configure the template path in a PHP application:

```php
require __DIR__ . '/vendor/autoload.php';

use Daycry\Twig\Configuration;
use Daycry\Twig\Twig;

$config = new Configuration();
$config->paths = [__DIR__ . '/templates'];
$config->cachePath = __DIR__ . '/var/cache/twig';

$twig = new Twig($config);
echo $twig->render('welcome', ['name' => 'Ada']);
```

Template names passed to the renderer omit `.twig`, the default extension.
Standalone configuration starts with no search paths and compiled caching disabled;
provide a writable cache path when enabling it. HTML escaping is enabled.

`saveData` defaults to true, so render parameters remain available to later
renders on that instance. Set it to false for independent contexts and scope the
renderer to its request or job. Native Twig loaders work through `withLoader()`;
see the [README examples](../README.md#standalone-php).

## CodeIgniter 4

From the application directory:

```bash
composer require daycry/twig-codeigniter4:^4.0
php spark twig:publish
```

The publisher creates `app/Config/Twig.php` extending
`Daycry\Twig\CodeIgniter4\Config\Twig`. Keep application-specific options in
that subclass. If upgrading, merge existing settings using the
[migration guide](MIGRATION.md).

Create `app/Views/welcome.twig` with the same template shown above. In a controller:

```php
return service('twig')->render('welcome', ['name' => 'Ada']);
```

The adapter supplies the default application view path, framework helpers, logger,
cache service integration and Spark commands. To use the global rendering helpers:

```php
helper('twig_helper');
return twig_render('welcome', ['name' => 'Ada']);
```

Register `Daycry\Twig\CodeIgniter4\Debug\Toolbar\Collectors\Twig` in your
application's toolbar collectors to enable its panel. Diagnostics and optional
panels are described in the [diagnostics reference](DIAGNOSTICS_REFERENCE.md).

## Local development before publication

The examples below assume the application and source checkout are sibling
directories and the checkout is named `twig`. Add path repositories to the
consuming application's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../twig/packages/codeigniter4",
            "options": {
                "symlink": true,
                "reference": "config",
                "versions": {"daycry/twig-codeigniter4": "4.0.0"}
            }
        },
        {
            "type": "path",
            "url": "../twig",
            "options": {
                "symlink": true,
                "reference": "config",
                "versions": {"daycry/twig": "4.0.0"}
            }
        }
    ]
}
```

Require `daycry/twig-codeigniter4:^4.0` for a CodeIgniter application. For standalone
PHP, retain only the core path repository and require `daycry/twig:^4.0`.
The version overrides are local testing aliases, not published releases.
Path installations depend on the checkout remaining available; use published
packages for deployment. The executable `tests/Consumers` examples verify both
installation combinations on PHP 8.2, 8.3, 8.4 and 8.5.
