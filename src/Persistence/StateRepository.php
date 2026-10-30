<?php

namespace Daycry\Twig\Persistence;

use Daycry\Twig\Contracts\CacheStoreInterface;
use Daycry\Twig\Support\AtomicFileWriter;
use Daycry\Twig\Support\PersistenceDecoder;
use RuntimeException;

final readonly class StateRepository
{
    private const FILES = [
        'compile.index'   => 'compile-index.json',
        'compile.sources' => 'compile-sources.json',
        'warmup.summary'  => 'warmup-summary.json',
        'invalidations'   => 'invalidations.json',
        'disc.stats'      => 'discovery-stats.json',
        'disc.list'       => 'discovery-stats-list.json',
    ];

    public function __construct(
        private ?string $directory = null,
        private ?CacheStoreInterface $store = null,
        private string $prefix = 'twig_',
        private int $ttl = 0,
    ) {
    }

    public function read(string $key): ?array
    {
        if ($this->store !== null) {
            return PersistenceDecoder::decode($this->store->get($this->prefix . $key));
        }
        $file = $this->path($key);
        $raw  = $file !== '' && is_file($file) ? @file_get_contents($file) : false;

        return PersistenceDecoder::decode($raw === false ? null : $raw);
    }

    public function write(string $key, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if ($this->store !== null) {
            if (! $this->store->save($this->prefix . $key, $json, $this->ttl)) {
                throw new RuntimeException('Unable to persist Twig state: ' . $key);
            }

            return;
        }
        $file = $this->path($key);
        if ($file === '') {
            return;
        }
        AtomicFileWriter::write($file, $json);
    }

    public function delete(string $key): bool
    {
        if ($this->store !== null) {
            return $this->store->delete($this->prefix . $key);
        }
        $file = $this->path($key);

        return $file === '' || ! is_file($file) || @unlink($file);
    }

    public function clear(): void
    {
        $failed = [];

        foreach (array_keys(self::FILES) as $key) {
            if (! $this->delete($key)) {
                $failed[] = $key;
            }
        }
        if ($failed !== []) {
            throw new RuntimeException('Unable to delete Twig state: ' . implode(', ', $failed));
        }
    }

    public function path(string $key): string
    {
        return $this->store !== null || $this->directory === null || $this->directory === ''
            ? '' : rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . self::FILES[$key];
    }

    public function medium(): string
    {
        return $this->store?->medium() ?? 'file';
    }
}
