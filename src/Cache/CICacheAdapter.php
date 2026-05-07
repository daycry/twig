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
 *  - Primary entry: <prefix><hash> => { "t": <timestamp>, "c": <php code>, "s": <hmac> }
 *  - Index key: <prefix>__index => list<string> of primary keys (for clear / tracking)
 *
 * Defense-in-depth: every payload is signed with HMAC-SHA256 using a key derived
 * from the application Encryption config (with stable fallback). On load(), the
 * signature is verified BEFORE the PHP code is evaluated, so a compromised cache
 * backend can no longer inject arbitrary code execution into the host process.
 */
class CICacheAdapter implements CacheInterface
{
    private readonly string $prefix;
    private readonly string $indexKey; // 0 = no expiry
    private readonly string $hmacKey;

    public function __construct(private readonly CICacheInterface $cache, string $prefix = 'twig_', private readonly int $ttl = 0, ?string $hmacKey = null)
    {
        $this->prefix   = rtrim($prefix, ':_') . '_';
        $this->indexKey = $this->prefix . '__index';
        $this->hmacKey  = $hmacKey !== null && $hmacKey !== '' ? $hmacKey : self::deriveDefaultHmacKey();
    }

    /**
     * Return human readable backend (handler class).
     */
    public function getBackendLabel(): string
    {
        return $this->cache::class;
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
     *
     * Verifies the HMAC signature before evaluating; tampered or unsigned legacy
     * entries are discarded (and logged) instead of executed.
     */
    public function load(string $key): void
    {
        $raw = $this->cache->get($key);
        if (! is_string($raw)) {
            return;
        }
        $data = json_decode($raw, true);
        if (! is_array($data) || ! isset($data['c']) || ! is_string($data['c'])) {
            return;
        }
        if (! $this->verifySignature($data)) {
            // Tampered or unsigned legacy entry — drop silently after logging.
            if (function_exists('log_message')) {
                log_message('warning', 'event=twig.cache.adapter.signature_invalid key=' . $key);
            }

            // Best-effort eviction so subsequent loads recompile.
            try {
                $this->cache->delete($key);
            } catch (Throwable $e) { // swallow
                if (function_exists('log_message')) {
                    log_message('debug', 'event=twig.cache.adapter.evict_failed key=' . $key . ' msg=' . $e->getMessage());
                }
            }

            return;
        }

        // Evaluate the cached template class. Using eval because we don't have a file path.
        // This mirrors how Array cache implementations handle ephemeral storage.
        try {
            eval('?>' . $data['c']);
        } catch (Throwable $e) { // ignore load failure
            if (function_exists('log_message')) {
                log_message('debug', 'event=twig.cache.adapter.eval_failed key=' . $key . ' msg=' . $e->getMessage());
            }
        }
    }

    /**
     * Write compiled PHP code to cache storage.
     */
    public function write(string $key, string $content): void
    {
        $payload = [
            't' => time(),
            'c' => $content,
            's' => $this->sign($content),
        ];
        $this->cache->save($key, json_encode($payload, JSON_UNESCAPED_SLASHES), $this->ttl);
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
        if (! is_array($data)) {
            return 0;
        }

        return (int) ($data['t'] ?? 0);
    }

    /**
     * Clear all twig-related compiled templates.
     */
    public function clear(): void
    {
        $idx = $this->readIndex();

        foreach ($idx as $k) {
            $this->cache->delete($k);
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

        return is_array($decoded) ? array_values(array_filter($decoded, is_string(...))) : [];
    }

    private function sign(string $content): string
    {
        return hash_hmac('sha256', $content, $this->hmacKey);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function verifySignature(array $data): bool
    {
        if (! isset($data['s']) || ! is_string($data['s']) || ! is_string($data['c'] ?? null)) {
            return false;
        }
        $expected = $this->sign($data['c']);

        return hash_equals($expected, $data['s']);
    }

    /**
     * Derive a stable HMAC key from the application Encryption config.
     * Falls back to a deterministic per-install value so integrity is preserved
     * even when the user has not configured an encryption key (the fallback is
     * not a secret and is intended only as a baseline against accidental
     * corruption / cross-environment key reuse).
     */
    private static function deriveDefaultHmacKey(): string
    {
        try {
            if (function_exists('config')) {
                $enc = config('Encryption');
                if ($enc !== null && property_exists($enc, 'key') && is_string($enc->key) && $enc->key !== '') {
                    return 'twig.cache|' . $enc->key;
                }
            }
        } catch (Throwable) { // swallow — fall through to fallback
        }
        $writePath = defined('WRITEPATH') ? WRITEPATH : '';
        $appPath   = defined('APPPATH') ? APPPATH : '';

        return 'twig.cache.fallback|' . hash('sha256', $writePath . '|' . $appPath);
    }
}
