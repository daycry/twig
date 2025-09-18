<?php

namespace Daycry\Twig\Cache;

use CodeIgniter\Cache\CacheInterface as CICacheInterface;
use Throwable;
use Twig\Cache\CacheInterface;

/**
 * Twig cache adapter that stores compiled templates in the configured
 * CodeIgniter cache handler (redis, memcached, etc) instead of filesystem.
 *
 * Key layout:
 *  - Primary entry: <prefix><hash> => { "t": <timestamp>, "c": <php code> }
 *  - Index key: <prefix>__index => list<string> of primary keys (for clear / tracking)
 */
class CICacheAdapter implements CacheInterface
{
    private CICacheInterface $cache;
    private string $prefix;
    private string $indexKey;
    private int $ttl; // 0 = no expiry

    public function __construct(CICacheInterface $cache, string $prefix = 'twig_', int $ttl = 0)
    {
        $this->cache    = $cache;
        $this->prefix   = rtrim($prefix, ':_') . '_';
        $this->indexKey = $this->prefix . '__index';
        $this->ttl      = $ttl;
    }

    /**
     * Return human readable backend (handler class).
     */
    public function getBackendLabel(): string
    {
        return get_class($this->cache);
    }

    /**
     * Generate a unique cache key for a given template & class name.
     */
    public function generateKey(string $name, string $className): string
    {
        // Use sha256 for low collision probability; include class + name
        return $this->prefix . hash('sha256', $className . '::' . $name);
    }

    /**
     * Load (include) the cached PHP code. Twig expects this to side-effect include the class, not return it.
     */
    public function load(string $key): void
    {
        $raw = $this->cache->get($key);
        if (! is_string($raw)) {
            return;
        }
        $data = json_decode($raw, true);
        if (! is_array($data) || ! isset($data['c'])) {
            return;
        }

        // Evaluate the cached template class. Using eval because we don't have a file path.
        // This mirrors how Array cache implementations handle ephemeral storage.
        try {
            eval('?>' . $data['c']);
        } catch (Throwable $e) { // ignore load failure
        }
    }

    /**
     * Write compiled PHP code to cache storage.
     */
    public function write(string $key, string $content): void
    {
        $payload = json_encode(['t' => time(), 'c' => $content], JSON_UNESCAPED_SLASHES);
        $this->cache->save($key, $payload, $this->ttl);
        // Maintain index (best-effort)
        $this->appendIndex($key);
    }

    /**
     * Return last modification timestamp or 0.
     */
    public function getTimestamp(string $key): int
    {
        $raw = $this->cache->get($key);
        if (! is_string($raw)) {
            return 0;
        }
        $data = json_decode($raw, true);

        return (int) ($data['t'] ?? 0);
    }

    /**
     * Clear all twig-related compiled templates.
     */
    public function clear(): void
    {
        $idx = $this->readIndex();
        if ($idx) {
            foreach ($idx as $k) {
                $this->cache->delete($k);
            }
        }
        $this->cache->delete($this->indexKey);
    }

    private function appendIndex(string $key): void
    {
        $idx = $this->readIndex();
        if (! in_array($key, $idx, true)) {
            $idx[] = $key;
        }
        $this->cache->save($this->indexKey, json_encode($idx), $this->ttl);
    }

    /**
     * @return list<string>
     */
    private function readIndex(): array
    {
        $raw = $this->cache->get($this->indexKey);
        if (! is_string($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
