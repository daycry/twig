# Cache behavior

The standalone renderer disables compiled caching by default. Supply an explicit
`Configuration::$cachePath` or `compiledCache` to enable it. The CodeIgniter
adapter defaults to `WRITEPATH . 'cache/twig'` for a FileHandler; other cache
handlers use `CICacheAdapter` and a framework metadata store.

## Compiled templates

Filesystem artifacts use a subclass of Twig's native cache. A persistent
`.twig-generation` marker makes whole-cache clearing visible to existing and new
renderers in a PHP worker. This marker is preserved when JSON metadata is reset. Keys are obtained from
that cache and the environment; the integration does not reproduce Twig's hashing
algorithm. `invalidateTemplate()`, batch invalidation and namespace invalidation
use Twig's removable-cache API and forget the corresponding logical index entry.
Loaded templates are refreshed in the current environment too.

`warmup()` and `warmupAll()` compile source and write it through the configured
cache without rendering templates. Cache existence, freshness and source
fingerprints decide whether compilation can be skipped. The `force` argument
writes compiled source again, including when its PHP class was already loaded.

## Metadata

`Persistence\StateRepository` manages these artifacts:

| Logical key | Filesystem filename |
|---|---|
| `compile.index` | `compile-index.json` |
| `compile.sources` | `compile-sources.json` |
| `disc.stats` | `discovery-stats.json` |
| `disc.list` | `discovery-stats-list.json` |
| `warmup.summary` | `warmup-summary.json` |
| `invalidations` | `invalidations.json` |

The compile index preserves the logical-name-to-boolean format. Source
fingerprints are separate and do not contain template source. File writes use
unique temporary files and atomic replacement, so readers do not see a partially
written JSON document. Whole-document metadata updates are advisory and can
replace concurrent updates; cache existence and source remain authoritative.

An optional `CacheStoreInterface` stores the same JSON payloads in a remote
backend. The adapter's implementation reports medium `ci` in diagnostics. Optional
metadata failures are logged and fall back to source discovery or recompilation.
In-process discovery hits do not write metadata.

## CodeIgniter remote compiled cache

Targeted invalidation deletes the actual compiled key. Whole-cache invalidation
rotates a random generation instead of maintaining a shared read-modify-write
array of every compiled key. Generation lookup is shared across all template
keys in one render and refreshed at the next operation; persistent renderers
observe external whole-cache clears. Old generations expire through retention: a positive
`cacheTtl` is honored, while zero gives compiled payloads a retention of **86400
seconds**. Metadata and generation keys can still have unlimited TTL.

Compiled PHP payloads retain HMAC verification. Configure a secret
`Config\Encryption::$key` for authentication. The deterministic fallback is only
corruption detection; it does not authenticate a cache controlled by an attacker.
Never store trusted template source in an untrusted writable location.

The adapter preserves the established logical prefix convention: `twig_`, or
`_twig_` if the framework's global prefix already ends in an underscore. The
framework handler is responsible for applying its global application prefix.

`clearCache()` removes compiled filesystem artifacts and known metadata, resets
local state and invalidates discovery. It returns the number of filesystem
artifacts removed, or zero for remote generation rotation. Set its reinitialize
argument when the current renderer will continue to be used.

Warmup writes the shared class identity even after an environment reset;
transient runtime class revisions do not become the authoritative warmup target.
Targeted invalidation refreshes the selected backend generation and removes both
the shared artifact and any distinct current-runtime artifact.

`resetDiagnostics()` resets optional metadata, with index/cache flags matching
`twig:reset-metrics`. The Spark command delegates to this API, including remote
backends; it does not scan an empty filesystem path or remove generation markers.
