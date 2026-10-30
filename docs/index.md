---
title: Daycry Twig
hide:
  - toc
---

<div class="hero" markdown>

# Daycry Twig

Twig template rendering for standalone PHP and CodeIgniter 4, with cache warmup,
discovery, invalidation and diagnostics.

[Install the core](INSTALLATION.md#standalone-php){ .md-button .md-button--primary }
[Use CodeIgniter 4](INSTALLATION.md#codeigniter-4){ .md-button }

PHP **8.2+** &nbsp; | &nbsp; Twig **3.30+** &nbsp; | &nbsp; Optional CodeIgniter **4.7+**

</div>

<div class="grid cards" markdown>

- **Standalone PHP**

    ---

    Configure template paths, use native Twig loaders, register functions and
    filters, and inject a PSR-3 logger without loading a framework.

    [Start rendering](INSTALLATION.md#standalone-php)

- **CodeIgniter 4 integration**

    ---

    Add shared services, configuration, rendering helpers, Spark commands,
    cache-service support and the debug toolbar with the optional adapter.

    [Install the adapter](INSTALLATION.md#codeigniter-4)

- **Cache lifecycle**

    ---

    Compile source without rendering, invalidate actual Twig artifacts and
    refresh template generations in persistent workers.

    [Understand caching](CACHING.md)

- **Discovery and diagnostics**

    ---

    Discover templates, inspect rendering profiles and choose a lean capability
    profile or deployment-versioned discovery for your application.

    [Read the diagnostics reference](DIAGNOSTICS_REFERENCE.md)

</div>

## Choose your package

| Package | Use it for |
|---|---|
| `daycry/twig` | Standalone PHP rendering and shared template tooling |
| `daycry/twig-codeigniter4` | CodeIgniter 4 integration; installs the core and framework dependency |

Both packages are developed in [daycry/twig](https://github.com/daycry/twig).
The adapter distribution is generated from `packages/codeigniter4`.

## Quick start

=== "Standalone PHP"

    ```bash
    composer require daycry/twig:^4.0
    ```

    ```php
    require __DIR__ . '/vendor/autoload.php';

    use Daycry\Twig\Configuration;
    use Daycry\Twig\Twig;

    $config = new Configuration();
    $config->paths = [__DIR__ . '/templates'];
    $twig = new Twig($config);

    echo $twig->render('welcome', ['name' => 'Ada']);
    ```

=== "CodeIgniter 4"

    ```bash
    composer require daycry/twig-codeigniter4:^4.0
    php spark twig:publish
    ```

    ```php
    return service('twig')->render('welcome', ['name' => 'Ada']);
    ```

Follow the [installation guide](INSTALLATION.md) to create the first template and
configure a cache path. Upgrading a 3.x application? Start with the
[4.0 migration guide](MIGRATION.md) because framework namespaces change.
