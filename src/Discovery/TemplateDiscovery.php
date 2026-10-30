<?php

namespace Daycry\Twig\Discovery;

use Daycry\Twig\Contracts\DiscoveryInterface;
use Daycry\Twig\Logging\TwigLogger;
use Daycry\Twig\Persistence\StateRepository;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use Twig\Loader\FilesystemLoader;

class TemplateDiscovery implements DiscoveryInterface
{
    private ?array $cache                 = null;
    private ?string $contextHash          = null;
    private int $hits                     = 0;
    private int $misses                   = 0;
    private int $invalidations            = 0;
    private ?int $persistedCount          = null;
    private ?string $cacheSource          = null;
    private ?string $persistedFingerprint = null;
    private bool $persistList             = false;
    private bool $useAPCu                 = false;
    private bool $loaded                  = false;
    private ?array $snapshot              = null;
    private StateRepository $state;
    private ?string $version = null;

    public function __construct(private readonly ?TwigLogger $log = null)
    {
        $this->state = new StateRepository();
    }

    public function configure(bool $persistList, bool $preload, bool $useAPCu, int $fingerprintDepth, ?string $version = null): void
    {
        $this->version     = $version;
        $this->persistList = $persistList;
        $this->useAPCu     = $useAPCu && function_exists('apcu_enabled') && apcu_enabled();
    }

    public function setRepository(StateRepository $state): void
    {
        $this->state       = $state;
        $this->loaded      = false;
        $this->cache       = $this->snapshot = null;
        $this->contextHash = null;
    }

    public function loadPersisted(): void
    {
        if ($this->loaded || ! $this->persistList) {
            return;
        }
        $this->loaded = true;

        try {
            $stats                = $this->state->read('disc.stats');
            $this->persistedCount = is_int($stats['persistedCount'] ?? null) ? $stats['persistedCount'] : null;
            $snapshot             = $this->state->read('disc.list');
            if (is_array($snapshot['list'] ?? null) && is_string($snapshot['fingerprint'] ?? null)
                && array_is_list($snapshot['list']) && count(array_filter($snapshot['list'], is_string(...))) === count($snapshot['list'])) {
                $this->snapshot             = $snapshot;
                $this->persistedFingerprint = $snapshot['fingerprint'];
            }
        } catch (Throwable $e) {
            $this->log?->debug('event=twig.discovery.load_failed', ['message' => $e->getMessage()]);
        }
    }

    public function invalidate(): void
    {
        if ($this->useAPCu && $this->persistedFingerprint !== null) {
            apcu_delete('twig.discovery.' . $this->persistedFingerprint);
        }
        $this->cache                = $this->snapshot = null;
        $this->contextHash          = null;
        $this->persistedFingerprint = null;
        $this->persistedCount       = null;
        $this->cacheSource          = null;
        $this->invalidations++;

        try {
            $this->state->delete('disc.list');
            $this->state->delete('disc.stats');
        } catch (Throwable $e) {
            $this->log?->debug('event=twig.discovery.invalidate_failed', ['message' => $e->getMessage()]);
        }
    }

    public function listAll(FilesystemLoader $loader, string $extension): array
    {
        $paths = [];

        foreach ($loader->getNamespaces() as $namespace) {
            $paths[$namespace] = $loader->getPaths($namespace);
        }
        $context = hash('sha256', json_encode([$paths, $extension, $this->version], JSON_THROW_ON_ERROR));
        if ($this->cache !== null && $context === $this->contextHash) {
            $this->hits++;

            return $this->cache;
        }
        $this->loadPersisted();
        if ($this->persistList && $this->version !== null) {
            $snapshot = $this->snapshot;
            if ($this->useAPCu) {
                $found  = false;
                $shared = apcu_fetch('twig.discovery.' . $context, $found);
                if ($found && is_array($shared) && array_is_list($shared)
                    && count(array_filter($shared, is_string(...))) === count($shared)) {
                    $this->cacheSource = 'apcu';
                    $snapshot          = ['fingerprint' => $context, 'list' => $shared];
                }
            }
            if ($snapshot !== null && $snapshot['fingerprint'] === $context) {
                $this->cache       = $snapshot['list'];
                $this->contextHash = $this->persistedFingerprint = $context;
                $this->cacheSource ??= 'persisted';
                $this->hits++;

                return $this->cache;
            }
        }
        // Include nested directory entries, including additions within the same mtime tick.
        $names = [];
        $tree  = [];

        foreach ($paths as $namespace => $directories) {
            foreach ($directories as $base) {
                if (! is_dir($base)) {
                    continue;
                }
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));

                foreach ($iterator as $file) {
                    if (! $file->isFile() || ! str_ends_with($file->getFilename(), $extension)) {
                        continue;
                    }
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($base, '/\\')) + 1));
                    $tree[]   = [$namespace, $base, $relative];
                    $name     = substr($relative, 0, -strlen($extension));
                    if ($namespace !== FilesystemLoader::MAIN_NAMESPACE) {
                        $name = '@' . $namespace . '/' . $name;
                    }
                    $names[$name] = true;
                }
            }
        }
        sort($tree);
        $fingerprint = $this->version !== null ? $context : hash('sha256', json_encode([$context, $tree], JSON_THROW_ON_ERROR));
        $this->loadPersisted();
        $this->contextHash          = $context;
        $this->persistedFingerprint = $fingerprint;
        if ($this->snapshot !== null && $this->snapshot['fingerprint'] === $fingerprint) {
            $this->cache       = $this->snapshot['list'];
            $this->cacheSource = 'persisted';
            $this->hits++;

            return $this->cache;
        }
        $this->cache = array_keys($names);
        sort($this->cache);
        $this->cacheSource = 'scan';
        $this->misses++;
        $this->persistedCount = count($this->cache);
        if ($this->persistList) {
            $this->snapshot = ['fingerprint' => $fingerprint, 'list' => $this->cache];

            try {
                $this->state->write('disc.stats', ['persistedCount' => count($this->cache)]);
                $this->state->write('disc.list', $this->snapshot);
                if ($this->useAPCu && $this->version !== null) {
                    apcu_store('twig.discovery.' . $context, $this->cache);
                }
            } catch (Throwable $e) {
                $this->log?->debug('event=twig.discovery.persist_failed', ['message' => $e->getMessage()]);
            }
        }

        return $this->cache;
    }

    public function getStats(): array
    {
        return [
            'hits'           => $this->hits, 'misses' => $this->misses, 'invalidations' => $this->invalidations,
            'cached'         => $this->cache !== null, 'count' => $this->cache !== null ? count($this->cache) : null,
            'persistedCount' => $this->persistedCount, 'fingerprint' => $this->persistedFingerprint, 'cache_source' => $this->cacheSource,
        ];
    }

    public function getPersistenceMedium(): string
    {
        return $this->state->medium();
    }
}
