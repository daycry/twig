# Daycry Twig: template rendering and framework services for CodeIgniter 4

CodeIgniter 4 services, configuration, helpers, Spark commands, cache-service
integration and optional debug toolbar for the standalone `daycry/twig` core.
Requires PHP 8.2+, CodeIgniter 4.7+ and daycry/twig 4.0.

Install with Composer:

```bash
composer require daycry/twig-codeigniter4:^4.0
php spark twig:publish
```

This installs the standalone core and the framework dependency. The generated
`app/Config/Twig.php` extends `Daycry\Twig\CodeIgniter4\Config\Twig`.

```php
$twig = service('twig');
echo $twig->render('welcome', ['name' => 'Ada']);
```

Helpers are available after `helper('twig_helper')`. Register
`Daycry\Twig\CodeIgniter4\Debug\Toolbar\Collectors\Twig` in the toolbar
collectors to display diagnostics.

Both packages are developed at [daycry/twig](https://github.com/daycry/twig).
This distribution is exported from `packages/codeigniter4` with `git subtree split`.
Submit changes to the source repository. See the
[installation guide](https://github.com/daycry/twig/blob/master/docs/INSTALLATION.md),
[migration guide](https://github.com/daycry/twig/blob/master/docs/MIGRATION.md) and
[release procedure](https://github.com/daycry/twig/blob/master/docs/RELEASING.md).

See [CHANGELOG.md](CHANGELOG.md) for package release notes.
