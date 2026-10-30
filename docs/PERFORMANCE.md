# Performance

The changes below remove identifiable work. Their effect on latency depends on
the application's templates and cache backend; no universal speedup is claimed.

- Production rendering in the CodeIgniter adapter returns before resolving debug
  filters or toolbar configuration.
- Discovery hits within a renderer return its in-memory names without rewriting
  the snapshot or statistics.
- Targeted invalidation resolves native cache keys, avoiding recursive scans and
  guessed filenames. Batch invalidation visits only the requested names.
- Remote cache clearing rotates a generation, avoiding an index whose complete
  contents would be read, searched and rewritten for every compilation.
- Remote cache generations are read once per render/warmup operation, rather
  than once per included template. Persistent workers refresh on the next operation.
- Lean mode avoids optional diagnostics and persistence. Warmup uses actual
  cache artifacts, preventing stale logical indexes from hiding a missing cache.

## Discovery freshness

Without an explicit deployment version, each new renderer enumerates nested
names before accepting a persisted snapshot. This catches additions, removals
and renames, including within a filesystem timestamp tick. Subsequent calls on
that renderer reuse memory. Call `resetTwig()` if a long-running renderer must
refresh its discovery after the tree changes.

For an immutable template tree controlled by deployment, set:

```php
$config->discoveryVersion = 'release-2026-10-30-1';
```

A matching persisted list, or APCu list when available, can then be used without
traversing directories. The version must change when template names change.
Paths, namespace precedence and the configured extension are part of its context.

## Measuring an application

Measure cold compilation separately from warm rendering. Compare the same
representative templates, PHP/OPcache configuration and cache backend. Record
latency, memory, remote cache operations and metadata writes. Use
`getDiagnostics()['performance']` for aggregate timings and optional per-template
statistics. The in-process discovery-write regression test verifies zero
additional writes on a repeated listing.

For development, `toolbarMinimal = true` on the adapter config suppresses
nonessential panels. `toolbarShowTemplates = false` avoids enumeration for the
templates table; `toolbarMaxTemplates` limits displayed rows, not the discovery
scan. Standalone instances have no toolbar integration.
