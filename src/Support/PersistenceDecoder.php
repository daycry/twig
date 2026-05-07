<?php

namespace Daycry\Twig\Support;

/**
 * Tolerant JSON decoder for persisted Twig state (compile index, warmup summary,
 * invalidations, discovery snapshots).
 *
 * Why this exists:
 *  - Cached payloads can be tampered with, truncated, or written by an older
 *    schema version — blindly trusting `json_decode` and accessing keys leads to
 *    fatal type errors in production.
 *  - Each call site previously did its own (often forgotten) `is_array` checks.
 */
final class PersistenceDecoder
{
    /**
     * Decode a JSON payload into an associative array. Returns `null` when the
     * payload is missing, empty, malformed, or not an object/array at the root.
     *
     * @return array<string,mixed>|null
     */
    public static function decode(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Decode and validate against a minimal schema describing required key types.
     * Schema entries are PHP type names (`'int'|'string'|'bool'|'array'|'float'`)
     * or `'?<type>'` for optional. Unknown keys are passed through unchanged.
     * If a required key is missing or has the wrong type, `null` is returned.
     *
     * Example:
     *   $data = PersistenceDecoder::decodeWithSchema($raw, [
     *       'cumulative' => 'int',
     *       'last'       => '?array',
     *   ]);
     *
     * @param array<string,string> $schema
     *
     * @return array<string,mixed>|null
     */
    public static function decodeWithSchema(?string $json, array $schema): ?array
    {
        $data = self::decode($json);
        if ($data === null) {
            return null;
        }

        foreach ($schema as $key => $type) {
            $optional = false;
            if (str_starts_with($type, '?')) {
                $optional = true;
                $type     = substr($type, 1);
            }
            if (! array_key_exists($key, $data)) {
                if (! $optional) {
                    return null;
                }

                continue;
            }
            if (! self::matchesType($data[$key], $type)) {
                return null;
            }
        }

        return $data;
    }

    private static function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'int'    => is_int($value),
            'float'  => is_float($value) || is_int($value),
            'string' => is_string($value),
            'bool'   => is_bool($value),
            'array'  => is_array($value),
            'null'   => $value === null,
            default  => false,
        };
    }
}
