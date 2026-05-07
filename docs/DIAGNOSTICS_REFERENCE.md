# Diagnostics Reference

`Twig::getDiagnostics()` returns an associative array describing the current
runtime state. Sections appear conditionally based on the active capability
profile (Lean vs Full + nullable overrides — see `docs/CACHING.md` §"Lean Mode
& Capability Overrides").

## Top-level keys

| Key | Type | Always present? | Description |
|-----|------|-----------------|-------------|
| `renders` | int | yes | Number of `render()` calls since process start |
| `last_render_view` | string\|null | yes | Logical name of the most recent render (with extension) |
| `environment_resets` | int | yes | Times `resetTwig()` was invoked |
| `cache` | object | yes | Cache backend snapshot — see below |
| `performance` | object | yes | Render-time aggregates — see below |
| `capabilities` | object | yes | Resolved capability flags after lean/override merge |
| `persistence` | object | yes | Storage medium per artifact (`file` or `ci`) |
| `discovery` | object | when `discoverySnapshot` capability is on | Discovery counters |
| `warmup` | object\|null | when `warmupSummary` capability is on | Last warmup result |
| `invalidations` | object\|null | when `invalidationHistory` capability is on | Last + cumulative |
| `dynamic_functions` | object | when `dynamicMetrics` capability is on | Active/pending counts |
| `dynamic_filters` | object | when `dynamicMetrics` capability is on | Active/pending counts |
| `static_functions` | object | when `extendedDiagnostics` capability is on | Configured count |
| `static_filters` | object | when `extendedDiagnostics` capability is on | Configured count |
| `extensions` | object | when `extendedDiagnostics` capability is on | configured + pending |
| `names` | object | when `extendedDiagnostics` capability is on | Concrete name lists |

## `cache`

| Key | Type | Description |
|-----|------|-------------|
| `enabled` | bool | Compilation cache is currently writing |
| `path` | string\|null | Filesystem path (null when backend is a CI service) |
| `mode` | "filesystem" \| "service" | Detected backend |
| `service_class` | string\|null | Concrete CI cache handler class (service mode) |
| `prefix` | string | Resolved key prefix (`twig_` or `_twig_`) |
| `ttl` | int | Cache TTL in seconds (0 = no expiry) |
| `compiled_templates` | int | Templates marked compiled in the in-process map |
| `reconstructed_index` | bool | True when index was rebuilt from filesystem at boot |

## `performance`

| Key | Type | Description |
|-----|------|-------------|
| `total_render_time_ms` | float | Sum across all renders this process |
| `avg_render_time_ms` | float | total / renders |
| `per_template` | object | (extendedDiagnostics) `{ template => { count, total_ms, avg_ms, max_ms } }` |
| `top_templates` | list | (extendedDiagnostics) Top 10 templates by `total_ms` |

## `discovery`

| Key | Type | Description |
|-----|------|-------------|
| `hits` | int | Cache hits on `listAll()` |
| `misses` | int | Cache misses (filesystem scan was needed) |
| `invalidations` | int | Times `invalidate()` was called |
| `count` | int\|null | Templates discovered in current process |
| `persistedCount` | int\|null | Templates discovered according to the snapshot |
| `cache_source` | "scan" \| "persisted" \| "persisted-preload" \| "apcu" \| null | Origin of the in-memory list |
| `persistence_medium` | "file" \| "ci" | Where the snapshot is stored |
| `fingerprint` | string\|null | sha1 fingerprint used to validate the snapshot |

## `warmup`

```json
{
    "summary":   { "compiled": int, "skipped": int, "errors": int, "error_details": [...] },
    "all":       bool,
    "timestamp": float
}
```

## `invalidations`

```json
{
    "last": {
        "type":      "single" | "batch" | "namespace",
        "removed":   int,
        "reinit":    bool,
        "timestamp": float
    },
    "cumulative_removed": int
}
```

## `dynamic_functions` / `dynamic_filters`

```json
{ "active": int, "pending": int }
```

## `names`

```json
{
    "static_functions":  ["base_url", ...],
    "static_filters":    [...],
    "dynamic_functions": [...],
    "dynamic_filters":   [...]
}
```

## `persistence`

```json
{
    "compile_index":     { "medium": "file" | "ci" },
    "discovery_snapshot": { "medium": "file" | "ci" },
    "warmup":            { "medium": "file" | "ci" },
    "invalidations":     { "medium": "file" | "ci" }
}
```
