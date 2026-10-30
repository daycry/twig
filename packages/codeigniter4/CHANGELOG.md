# Changelog

## [Unreleased]

## [4.0.0] - 2026-10-30

### Added
- Separate `daycry/twig-codeigniter4` package requiring the standalone core `^4.0`.
- CodeIgniter services, configuration, helpers, Spark commands, cache adapter and debug toolbar integration.
- Distribution generated from `packages/codeigniter4` in the source repository.

### Changed
- Framework classes use the `Daycry\Twig\CodeIgniter4` namespace.
- Remote compiled cache clearing rotates generations; entries with TTL zero have a retention of 86400 seconds.
- Production rendering skips debug toolbar lookups.
- Diagnostic reset delegates to the configured cache backend.

### Upgrade
Install `daycry/twig-codeigniter4:^4.0` and migrate framework imports and
application configuration using the
[4.0 migration guide](https://github.com/daycry/twig/blob/v4.0.0/docs/MIGRATION.md).
Requires PHP 8.2+, CodeIgniter 4.7+ and Twig 3.30+ through the core dependency.
