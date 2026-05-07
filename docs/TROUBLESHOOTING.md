# Troubleshooting

Common problems and how to diagnose them. Also see `docs/CACHING.md` and
`docs/PERFORMANCE.md` for the conceptual model.

> Run `php spark twig:doctor` first — it surfaces most of the issues below
> automatically.

## "No templates discovered"

`Twig::listTemplates()` returns an empty array, `twig:warmup --all` does
nothing, the toolbar template panel is empty.

1. Confirm `Config\Twig::$paths` contains existing, readable directories:
   ```bash
   php spark twig:doctor
   ```
2. If you use namespaced paths, the syntax is `[<absolute path>, '<namespace>']`
   — not `'<namespace>' => <path>'`. Wrong syntax silently degrades to no paths.
3. Lean Mode disables the discovery snapshot. If you previously relied on a
   persisted list, the first request after enabling Lean re-scans from disk; a
   broken path will surface as "empty list" rather than "stale snapshot".

## Compiled cache never cleared

`twig:clear-cache` reports "No cache files found".

1. The active backend may not be the filesystem. Check `getDiagnostics()['cache']['mode']`
   — when it's `service`, compiled templates live in Redis/Memcached and the
   filesystem path is empty by design. Use `twig:clear-cache` (which routes
   through the adapter) and not manual `rm -rf`.
2. The cache prefix derives from `Config\Cache::$prefix`. If the global prefix
   ends with `_`, compiled keys live under `_twig_<sha>`; otherwise under
   `twig_<sha>`. A mismatch between writer and reader will look like "nothing to
   clear" / "always recompiles".

## Cache writes succeed but reads always recompile

Likely cause on the CI cache backend: a previous version wrote unsigned
payloads. Since v3.x the adapter requires HMAC and silently discards entries
without a valid signature (logging `event=twig.cache.adapter.signature_invalid`
at warning level).

Fix: run `php spark twig:clear-cache --reinit` once. New entries will be signed.

## "Method ReflectionProperty::setAccessible() is deprecated since 8.5"

The `setAccessible()` call became a no-op in PHP 8.1+. Tests calling it on
PHP ≥ 8.5 emit a deprecation. Either remove the call (recommended — the
bundled tests already do) or run on a 8.2–8.4 toolchain.

## APCu warns "not enabled"

`twig:doctor` reports `[WARNING] apcu — APCu extension installed but not enabled`.
Set `apc.enabled=1` and `apc.enable_cli=1` (the second matters for `spark`
warmup commands). Without APCu the discovery snapshot still works via
filesystem; only the in-process accelerator is missing.

## `twig:warmup` reports zero compiled but templates do exist

1. Templates already compiled. Add `--force` to recompile.
2. The supplied logical names are wrong (typo, missing namespace prefix). Run
   `twig:list` to see what discovery actually found.
3. With CI cache backend the `compile-index.json` is reconstructed at startup
   when keys are present but the index is missing — `twig:doctor` will warn
   `Compile index was reconstructed`; running `twig:warmup --all` rebuilds it.

## Logs are missing after upgrading to v3

v3 normalized log lines to `event=<name> key=value`. Ad-hoc human messages were
removed. Update any log parser that grepped for the old text.

## `getDiagnostics()` is huge

In Lean Mode (`leanMode = true`) only the core sections are present. In Full
Mode you can selectively turn off `enableExtendedDiagnostics`,
`enableDynamicMetrics`, `enableInvalidationHistory`, `enableWarmupSummary`,
`enableDiscoverySnapshot` to drop sections.

## CLI command exits 0 even on failure

Pre-3.x commands silently returned without an exit code. Since v3 the commands
return `EXIT_SUCCESS` (0), `EXIT_USER_INPUT` (7) or `EXIT_ERROR` (1). If you
see exit code 0 after an obvious error, you may be running an older copy from
your vendor cache: `composer dump-autoload` and re-run.
