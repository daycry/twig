# Diagnostics reference

`$twig->getDiagnostics()` is available on both the standalone renderer and the
CodeIgniter adapter. It returns these base sections:

| Key | Meaning |
|---|---|
| `renders` | Successful render count on this instance |
| `last_render_view` | Last successfully rendered view, or null before any render |
| `environment_resets` | Explicit environment resets |
| `cache` | Enabled state, path/backend, prefix, TTL, indexed template count, reconstruction flag |
| `performance` | Total/average render timing; per-template details when enabled |
| `capabilities` | Resolved full/lean profile with explicit overrides |
| `persistence` | Actual metadata medium for enabled artifacts |

Full mode also includes `names`, `static_functions`, `static_filters`,
`extensions`, `dynamic_functions`, `dynamic_filters`, `discovery`, `warmup` and
`invalidations` as their capabilities permit. Lean mode omits disabled sections
rather than returning empty placeholders.

`cache.compiled_templates` is an advisory logical index count. Inspect real cache
artifacts or run warmup to verify availability. `reconstructed_index` means that
compiled files existed but a logical index could not be loaded. Filesystem
reconstruction uses synthetic `unknown_*` names; warmup restores useful names.

Discovery reports request-local hits, misses, invalidations, list counts,
fingerprint and source (`scan`, `persisted` or `apcu`). Its `persistence_medium`
and the persistence map report `file`, `ci`, or the label supplied by a custom
metadata store. Re-reading diagnostics does not overwrite live discovery hits.

The CI4 collector lives at
`Daycry\Twig\CodeIgniter4\Debug\Toolbar\Collectors\Twig` and resolves the adapter's
shared service. Use the adapter's toolbar flags to limit its rendered sections.
