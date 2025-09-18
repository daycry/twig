<?php

namespace Daycry\Twig\Discovery;

use CodeIgniter\Cache\CacheInterface;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionObject;
use SplFileInfo;
use Throwable;
use Twig\Loader\FilesystemLoader;

/**
 * TemplateDiscovery encapsulates template enumeration & in-process caching.
 * Enhanced with optional list persistence, fingerprinting and APCu reuse.
 */
class TemplateDiscovery
{
    /**
     * @var list<string>|null
     */
    private ?array $cache = null;

    private ?string $contextHash = null;
    private int $hits            = 0;
    private int $misses          = 0;
    private int $invalidations   = 0;

    /**
     * Stats persistence path
     */
    private ?string $persistPath = null;

    private ?int $persistedCount = null;

    /**
     * Persisted list snapshot path
     */
    private ?string $listSnapshotPath = null;

    /**
     * Last persisted fingerprint
     */
    private ?string $persistedFingerprint = null;

    /**
     * Config flags
     */
    private bool $persistList = false;

    private bool $preload         = false;
    private bool $useAPCu         = false;
    private int $fingerprintDepth = 0;

    /**
     * Source of current cache: scan|persisted|apcu
     */
    private ?string $cacheSource = null;

    /**
     * CI cache backend (optional when using backend=ci)
     */
    private ?CacheInterface $ciCache = null;

    private string $ciPrefix = 'twig_';
    private int $ciTtl       = 0; // 0 = no expiry

    /**
     * Persistence medium: file|ci
     */
    private string $persistMedium = 'file';

    // (debug trace removed)
    /**
     * Whether list was preloaded from snapshot (contextHash not yet bound)
     */
    private bool $preloaded = false;

    public function configure(bool $persistList, bool $preload, bool $useAPCu, int $fingerprintDepth): void
    {
        $this->persistList      = $persistList;
        $this->preload          = $preload;
        $this->useAPCu          = $useAPCu && function_exists('apcu_enabled') && apcu_enabled();
        $this->fingerprintDepth = max(0, $fingerprintDepth);
    }

    public function setPersistPath(?string $path): void
    {
        $this->persistPath = $path;
        if ($path) {
            $this->listSnapshotPath = preg_replace('/(\.json)$/', '-list.json', $path) ?: ($path . '-list.json');
        }
    }

    /**
     * Enable CI cache persistence (overrides file persistence).
     */
    public function useCiCache(CacheInterface $cache, string $prefix, int $ttl = 0): void
    {
        $this->ciCache       = $cache;
        $this->ciPrefix      = rtrim($prefix, ':_') . '_';
        $this->ciTtl         = $ttl;
        $this->persistMedium = 'ci';

        // Attempt one-time migration of existing file-based persistence if present and CI keys empty
        try {
            if ($this->persistPath && is_file($this->persistPath)) {
                $existingCi = $this->ciCache->get($this->ciPrefix . 'disc.stats');
                if ($existingCi === null) {
                    $json = @file_get_contents($this->persistPath);
                    if (is_string($json) && $json !== '') {
                        $this->ciCache->save($this->ciPrefix . 'disc.stats', $json, $this->ciTtl);
                    }
                }
            }
            if ($this->persistList && $this->listSnapshotPath && is_file($this->listSnapshotPath)) {
                $existingList = $this->ciCache->get($this->ciPrefix . 'disc.list');
                if ($existingList === null) {
                    $json = @file_get_contents($this->listSnapshotPath);
                    if (is_string($json) && $json !== '') {
                        $this->ciCache->save($this->ciPrefix . 'disc.list', $json, $this->ciTtl);
                    }
                }
            }
        } catch (Throwable $e) { // ignore migration errors
        }
    }

    public function getPersistenceMedium(): string
    {
        return $this->persistMedium;
    }

    private function persist(): void
    {
        $data = [
            'hits'           => $this->hits,
            'misses'         => $this->misses,
            'invalidations'  => $this->invalidations,
            'cached'         => $this->cache !== null,
            'count'          => $this->cache !== null ? count($this->cache) : ($this->persistedCount ?? null),
            'persistedCount' => $this->cache !== null ? count($this->cache) : ($this->persistedCount ?? null),
            'fingerprint'    => $this->persistedFingerprint,
        ];
        if ($this->persistMedium === 'ci' && $this->ciCache) {
            try {
                $this->ciCache->save($this->ciPrefix . 'disc.stats', json_encode($data, JSON_UNESCAPED_SLASHES), $this->ciTtl);
            } catch (Throwable $e) { // ignore
            }
            if ($this->persistList && $this->cache !== null) {
                try {
                    $this->ciCache->save($this->ciPrefix . 'disc.list', json_encode(['fingerprint' => $this->persistedFingerprint, 'list' => $this->cache], JSON_UNESCAPED_SLASHES), $this->ciTtl);
                } catch (Throwable $e) { // ignore
                }
            }
        } else {
            if (! $this->persistPath) {
                return;
            }

            try {
                @file_put_contents($this->persistPath, json_encode($data, JSON_UNESCAPED_SLASHES));
            } catch (Throwable $e) { // ignore
            }
            if ($this->persistList && $this->cache !== null && $this->listSnapshotPath) {
                try {
                    @file_put_contents($this->listSnapshotPath, json_encode(['fingerprint' => $this->persistedFingerprint, 'list' => $this->cache], JSON_UNESCAPED_SLASHES));
                } catch (Throwable $e) { // ignore
                }
            }
        }
        if ($this->useAPCu && $this->cache !== null && $this->persistedFingerprint) {
            try {
                if (function_exists('apcu_store')) {
                    apcu_store($this->apcuKey($this->persistedFingerprint), $this->cache);
                }
            } catch (Throwable $e) { // ignore
            }
        }
    }

    public function loadPersisted(): void
    {
        if ($this->persistMedium === 'ci' && $this->ciCache) {
            try {
                $json = $this->ciCache->get($this->ciPrefix . 'disc.stats');
                if (is_string($json)) {
                    $data = json_decode($json, true);
                    if (is_array($data)) {
                        $this->hits                 = (int) ($data['hits'] ?? $this->hits);
                        $this->misses               = (int) ($data['misses'] ?? $this->misses);
                        $this->invalidations        = (int) ($data['invalidations'] ?? $this->invalidations);
                        $this->persistedCount       = isset($data['persistedCount']) ? (int) $data['persistedCount'] : (isset($data['count']) ? (int) $data['count'] : $this->persistedCount);
                        $this->persistedFingerprint = $data['fingerprint'] ?? $this->persistedFingerprint;
                    }
                }
            } catch (Throwable $e) { // ignore
            }
        } else {
            if (! $this->persistPath || ! is_file($this->persistPath)) {
                return;
            }

            try {
                $json = @file_get_contents($this->persistPath);
                if ($json === false) {
                    return;
                }
                $data = json_decode($json, true);
                if (! is_array($data)) {
                    return;
                }
                $this->hits                 = (int) ($data['hits'] ?? $this->hits);
                $this->misses               = (int) ($data['misses'] ?? $this->misses);
                $this->invalidations        = (int) ($data['invalidations'] ?? $this->invalidations);
                $this->persistedCount       = isset($data['persistedCount']) ? (int) $data['persistedCount'] : (isset($data['count']) ? (int) $data['count'] : $this->persistedCount);
                $this->persistedFingerprint = $data['fingerprint'] ?? $this->persistedFingerprint;
            } catch (Throwable $e) { // ignore
            }
        }
        if ($this->persistList && $this->preload && $this->cache === null) {
            $this->attemptPreloadList();
        }
    }

    /**
     * Invalidate current discovery cache explicitly.
     */
    public function invalidate(): void
    {
        $this->cache       = null;
        $this->contextHash = null;
        $this->invalidations++;
        $this->cacheSource = null;
    }

    /**
     * Returns list of logical template names (without extension) for provided loader & extension.
     *
     * @return list<string>
     */
    public function listAll(FilesystemLoader $loader, string $extension): array
    {
        $pathsMap = $this->extractPathsMap($loader);
        if ($pathsMap === []) {
            return [];
        }
        $canonical = $this->canonicalizePathsMap($pathsMap);
        $hash      = md5($loader::class . '|' . $extension . '|' . json_encode($canonical));
        // debug logging removed
        if ($this->cache !== null && $this->contextHash === $hash) {
            $this->hits++;
            $this->persist();

            return $this->cache; // cache hit
        }
        // Case: we preloaded a snapshot earlier (cache filled but no contextHash yet)
        if ($this->cache !== null && $this->contextHash === null && $this->preloaded) {
            $currentFp = $this->computeFingerprint($pathsMap);
            if ($this->persistedFingerprint && $currentFp === $this->persistedFingerprint) {
                $this->contextHash = $hash;
                $this->hits++;
                // Distinguish source for diagnostics clarity
                if ($this->cacheSource === 'persisted') {
                    $this->cacheSource = 'persisted-preload';
                }
                $this->persist();

                return $this->cache;
            }
            // fall through to scan path
        }
        // Try restore from persisted/APCu if enabled and fingerprint matches
        if ($this->cache === null && $this->persistList && $this->persistedFingerprint) {
            $currentFp = $this->computeFingerprint($pathsMap);
            if ($currentFp === $this->persistedFingerprint) {
                if ($this->useAPCu && function_exists('apcu_fetch')) {
                    try {
                        $ok   = false;
                        $apcu = apcu_fetch($this->apcuKey($currentFp), $ok);
                        if ($ok && is_array($apcu)) {
                            $this->cache       = $apcu;
                            $this->contextHash = $hash;
                            $this->hits++;
                            $this->cacheSource = 'apcu';
                            $this->persist();

                            return $this->cache;
                        }
                    } catch (Throwable $e) { // ignore
                    }
                }
                $restored = $this->restoreListSnapshot();
                if ($restored !== null) {
                    $this->cache       = $restored;
                    $this->contextHash = $hash;
                    $this->hits++;
                    $this->cacheSource = 'persisted';
                    $this->persist();

                    return $this->cache;
                }
            }
        }
        $this->misses++;
        $extLen = strlen($extension);
        $out    = [];

        foreach ($pathsMap as $ns => $paths) {
            foreach ($paths as $base) {
                if (! is_dir($base)) {
                    continue;
                }
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));

                /** @var SplFileInfo $file */
                foreach ($it as $file) {
                    if (! $file->isFile()) {
                        continue;
                    }
                    $filePath = $file->getPathname();
                    if (substr($filePath, -$extLen) !== $extension) {
                        continue;
                    }
                    $rel     = ltrim(str_replace(['\\', '/'], '/', substr($filePath, strlen($base))), '/');
                    $logical = substr($rel, 0, -$extLen);
                    if ($ns !== FilesystemLoader::MAIN_NAMESPACE) {
                        $logical = '@' . $ns . '/' . $logical;
                    }
                    $out[] = $logical;
                }
            }
        }
        $this->cache       = $out;
        $this->contextHash = $hash;
        if ($this->persistList) {
            $this->persistedFingerprint = $this->computeFingerprint($pathsMap);
        }
        $this->cacheSource = 'scan';
        $this->persist();

        return $out;
    }

    /**
     * Return discovery internal stats.
     */
    public function getStats(): array
    {
        return [
            'hits'           => $this->hits,
            'misses'         => $this->misses,
            'invalidations'  => $this->invalidations,
            'cached'         => $this->cache !== null,
            'count'          => $this->cache !== null ? count($this->cache) : null,
            'persistedCount' => $this->persistedCount,
            'fingerprint'    => $this->persistedFingerprint,
            'cache_source'   => $this->cacheSource,
        ];
    }

    /**
     * Helper to extract internal paths map from loader via reflection.
     */
    private function extractPathsMap(FilesystemLoader $loader): array
    {
        try {
            $ref = new ReflectionObject($loader);
            if (! $ref->hasProperty('paths')) {
                return [];
            }
            $prop = $ref->getProperty('paths');
            $prop->setAccessible(true);
            $pathsMap = $prop->getValue($loader);

            return is_array($pathsMap) ? $pathsMap : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function computeFingerprint(array $pathsMap): string
    {
        // Canonicalize namespace ordering and path ordering to produce stable fingerprint
        $canonicalPathsMap = [];
        $namespaces        = array_keys($pathsMap);
        sort($namespaces); // stable order

        foreach ($namespaces as $ns) {
            $paths = $pathsMap[$ns];
            if (! is_array($paths)) {
                continue;
            }
            $sorted = array_values(array_filter($paths, 'is_string'));
            sort($sorted);
            $canonicalPathsMap[$ns] = $sorted;
        }
        $result = [];

        foreach ($canonicalPathsMap as $ns => $paths) {
            $nsOut = [];

            foreach ($paths as $base) {
                $nsOut[$base] = $this->sampleDirMtime($base, $this->fingerprintDepth);
            }
            ksort($nsOut);
            $result[$ns] = $nsOut;
        }
        ksort($result);

        return sha1(json_encode([$canonicalPathsMap, $result]));
    }

    private function sampleDirMtime(string $dir, int $depth): int
    {
        if (! is_dir($dir)) {
            return 0;
        }
        $mt = @filemtime($dir) ?: 0;
        if ($depth <= 0) {
            return $mt;
        }
        $queue = [[$dir, 0]];

        while ($queue) {
            [$current,$d] = array_shift($queue);
            if ($d >= $depth) {
                continue;
            }
            $items = @scandir($current) ?: [];

            foreach ($items as $it) {
                if ($it === '.' || $it === '..') {
                    continue;
                }
                $full = $current . DIRECTORY_SEPARATOR . $it;
                if (is_dir($full)) {
                    $mt ^= (@filemtime($full) ?: 0);
                    $queue[] = [$full, $d + 1];
                }
            }
        }

        return $mt;
    }

    private function restoreListSnapshot(): ?array
    {
        if ($this->persistMedium === 'ci' && $this->ciCache) {
            try {
                $json = $this->ciCache->get($this->ciPrefix . 'disc.list');
                if (! is_string($json)) {
                    return null;
                }
                $data = json_decode($json, true);
                if (! is_array($data) || ! isset($data['list']) || ! is_array($data['list'])) {
                    return null;
                }
                if (($data['fingerprint'] ?? null) !== $this->persistedFingerprint) {
                    return null;
                }

                return $data['list'];
            } catch (Throwable $e) {
                return null;
            }
        } else {
            if (! $this->listSnapshotPath || ! is_file($this->listSnapshotPath)) {
                return null;
            }

            try {
                $json = @file_get_contents($this->listSnapshotPath);
                if ($json === false) {
                    return null;
                }
                $data = json_decode($json, true);
                if (! is_array($data) || ! isset($data['list']) || ! is_array($data['list'])) {
                    return null;
                }
                if (($data['fingerprint'] ?? null) !== $this->persistedFingerprint) {
                    return null;
                }

                return $data['list'];
            } catch (Throwable $e) {
                return null;
            }
        }
    }

    private function attemptPreloadList(): void
    {
        $restored = $this->restoreListSnapshot();
        if ($restored !== null) {
            $this->cache       = $restored;
            $this->cacheSource = 'persisted';
            $this->preloaded   = true;
        }
    }

    private function apcuKey(string $fingerprint): string
    {
        return 'twig.discovery.list.' . $fingerprint;
    }

    /**
     * Canonical normalization for context hash: resolve real paths, sort, unique.
     */
    private function canonicalizePathsMap(array $pathsMap): array
    {
        $out        = [];
        $namespaces = array_keys($pathsMap);
        sort($namespaces);

        foreach ($namespaces as $ns) {
            $paths = $pathsMap[$ns];
            if (! is_array($paths)) {
                continue;
            }
            $norm = [];

            foreach ($paths as $p) {
                if (! is_string($p)) {
                    continue;
                }
                $rp        = realpath($p) ?: $p;
                $rp        = rtrim(str_replace(['\\', '/'], '/', $rp), '/');
                $norm[$rp] = true;
            }
            $list = array_keys($norm);
            sort($list);
            $out[$ns] = $list;
        }

        return $out;
    }
}
