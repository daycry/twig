<?php

namespace Tests\Support;

use CodeIgniter\Cache\Handlers\DummyHandler;

final class ArrayCache extends DummyHandler
{
    private array $values      = [];
    public int $getCalls       = 0;
    public int $compiledWrites = 0;
    public array $ttls         = [];

    public function get(string $key): mixed
    {
        $this->getCalls++;

        return $this->values[$key] ?? null;
    }

    public function save(string $key, mixed $value, int $ttl = 60): bool
    {
        $payload = is_string($value) ? json_decode($value, true) : null;
        if (is_array($payload) && isset($payload['t'], $payload['c'], $payload['s'])) {
            $this->compiledWrites++;
        }
        $this->ttls[$key]   = $ttl;
        $this->values[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }
}
