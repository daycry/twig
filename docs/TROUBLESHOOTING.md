# Troubleshooting

## Framework classes are missing after upgrading

Version 4 provides CodeIgniter integration through `daycry/twig-codeigniter4`,
developed in `packages/codeigniter4` in the source repository. Install the adapter
and update namespaces using [MIGRATION.md](MIGRATION.md). The core
`Daycry\Twig\Twig` deliberately does not load framework helpers or resolve services.

## Standalone templates cannot be found

Set `Configuration::$paths` to existing directories or call `withLoader()`.
Names passed to `render()` omit the extension. Names passed directly to the
native Twig environment include it. Use `addPath($path, 'admin')` and
`render('@admin/name')` for namespaced templates.

## Warmup skips a template unexpectedly

Inspect the actual backend and cache key instead of relying solely on the compile
index. Missing artifacts are recompiled. `warmup($names, true)` compiles source
again. Set `TWIG_WARMUP_VERBOSE=1` for per-template error details. Warmup compiles
but never renders templates or executes registered template functions.

## A new nested template does not appear

Without a deployment version, construct a new renderer or reset discovery with
`resetTwig()`. If `discoveryVersion` is configured, update it whenever the deployed
template tree changes. A repeated listing on the same renderer intentionally
uses its in-memory list.

## Data appears in a later render

`saveData` defaults to true. Set it to false when render calls should be isolated.
Use one renderer per request/job in persistent workers, or deliberately manage
its retained context.

## Remote cache clearing reports zero

Whole-cache clearing rotates a generation and cannot report the number of remote
objects made obsolete. It does not clear unrelated application cache. Old
compiled payloads expire automatically; see [CACHING.md](CACHING.md).

## Checking CodeIgniter discovery

Run `php spark list` and inspect the `twig:*` commands. Check that Composer loads
`Daycry\Twig\CodeIgniter4` and the framework has Composer discovery enabled.
`helper('twig_helper')` should resolve the adapter's helper file. `twig:doctor`,
`twig:lint` and `twig:diagnostics --json` help inspect runtime configuration.
