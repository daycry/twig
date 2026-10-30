<?php

namespace Daycry\Twig\CodeIgniter4\Cache;

use CodeIgniter\Cache\CacheInterface;
use Daycry\Twig\Contracts\CacheStoreInterface;

final readonly class CacheStore implements CacheStoreInterface
{
    public function __construct(private CacheInterface $cache)
    {
    }

    public function get(string $key): ?string
    {
        $value = $this->cache->get($key);

        return is_string($value) ? $value : null;
    }

    public function save(string $key, string $value, int $ttl): bool
    {
        return $this->cache->save($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->cache->delete($key) || $this->cache->get($key) === null;
    }

    public function medium(): string
    {
        return 'ci';
    }

    public function backend(): string
    {
        return $this->cache::class;
    }
}
