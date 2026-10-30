# Core services and framework integration

`Daycry\Twig\Twig` owns rendering context and coordinates independent services:

| Service | Responsibility |
|---|---|
| `Configuration` / `Configuration\TwigOptions` | Explicit framework-independent settings |
| `Discovery\TemplateDiscovery` | Enumerate loader paths, track request-local hits, validate snapshots |
| `Cache\TemplateCacheManager` | Track logical compile state; it is not proof that a cache artifact exists |
| `Warmup\TemplateWarmer` | Compile source, check artifact freshness and preserve source fingerprints |
| `Invalidation\TemplateInvalidator` | Remove artifacts through native Twig keys and refresh loaded templates |
| `Persistence\StateRepository` | Read/write metadata through JSON files or `Contracts\CacheStoreInterface` |
| `Registry\DynamicRegistry` | Retain dynamic functions and filters across environment resets |
| `Logging\TwigLogger` | Optional injected PSR-3 logging |
| `Profile\RenderProfiler` | Bounded per-template timing aggregation |

Configuration is resolved before the cache manager, warmer and invalidator are
wired. They share one manager. Persistent loading is scoped to each renderer;
a previous instance cannot suppress initialization in another instance.

To supply metadata storage, implement `CacheStoreInterface` and pass it as the
third renderer constructor argument. A custom compiled Twig cache is configured
separately through `Configuration::$compiledCache`. A remote compiled cache must
implement Twig's removable cache interface for targeted invalidation. Implement
`ClearableCacheInterface` when it also supports clearing its entire namespace. Implement
`RefreshableCacheInterface` for distributed invalidation: the renderer refreshes
it once per render/warmup operation and rebuilds loaded templates if it changed.

The optional adapter is in `packages/codeigniter4/src`, under
`Daycry\Twig\CodeIgniter4`. Its configuration converts to the core configuration;
its renderer subclasses the core to install helpers, logger, event notification,
cache-service storage and debug toolbar behavior. Services, Spark commands,
configuration publishing and the Viewable trait belong to this adapter only.

The architecture check forbids dependencies from `src/` on framework or adapter
classes. Consumer checks also install and execute the core without CodeIgniter.

`Runtime\Environment` adds an isolated class revision after an explicit reset.
PHP classes cannot be unloaded; the revision prevents loaded templates and their
includes from retaining old source. Normal fresh instances use stable Twig keys.
Globals and dynamic registrations are reapplied when the environment is rebuilt.

`Cache\FilesystemCache` shares generation changes through a fixed marker and
`Support\AtomicFileWriter` performs atomic metadata replacement. Warmup compiles
shared persistent class identities separately from runtime reset revisions.
`resetDiagnostics()` is the common backend-aware maintenance API used by Spark.
