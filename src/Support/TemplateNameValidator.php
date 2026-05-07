<?php

namespace Daycry\Twig\Support;

use InvalidArgumentException;

/**
 * Centralized validation for logical template names received via public APIs and CLI.
 *
 * Goals:
 *  - Reject path traversal sequences (`..`, leading `/`) that could let an attacker escape
 *    the configured Twig namespace roots when the name is fed to filesystem operations.
 *  - Reject null bytes and stray whitespace that change interpretation downstream.
 *  - Permit Twig's namespace syntax (`@namespace/path/to/template`) and standard relative paths.
 */
final class TemplateNameValidator
{
    /**
     * Allowed characters: letters, digits, underscore, hyphen, dot, slash, leading `@` for namespaces.
     */
    private const PATTERN = '#^@?[\w\-]+(?:[/.][\w\-]+)*$#u';

    /**
     * @throws InvalidArgumentException when the name violates the policy.
     */
    public static function assertValid(string $name, string $field = 'template'): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new InvalidArgumentException(sprintf('%s name cannot be empty.', ucfirst($field)));
        }
        if (str_contains($trimmed, "\0")) {
            throw new InvalidArgumentException(sprintf('%s name contains a null byte.', ucfirst($field)));
        }
        // Reject any `..` segment, even when surrounded by slashes (covers `..`, `../x`, `x/../y`).
        if ($trimmed === '..' || str_contains($trimmed, '/../') || str_starts_with($trimmed, '../') || str_ends_with($trimmed, '/..')) {
            throw new InvalidArgumentException(sprintf('%s name contains a path traversal sequence: %s', ucfirst($field), $name));
        }
        if (str_starts_with($trimmed, '/') || str_starts_with($trimmed, '\\')) {
            throw new InvalidArgumentException(sprintf('%s name must be relative (received: %s).', ucfirst($field), $name));
        }
        if (preg_match(self::PATTERN, $trimmed) !== 1) {
            throw new InvalidArgumentException(sprintf('%s name "%s" contains forbidden characters.', ucfirst($field), $name));
        }

        return $trimmed;
    }

    /**
     * Filter and validate a list of names. Invalid entries are dropped (with optional callback).
     *
     * @param list<string>                      $names
     * @param callable(string,string):void|null $onInvalid Optional callback receiving (rawName, errorMessage)
     *
     * @return list<string>
     */
    public static function filterValid(array $names, ?callable $onInvalid = null): array
    {
        $valid = [];

        foreach ($names as $raw) {
            if (! is_string($raw)) {
                continue;
            }

            try {
                $valid[] = self::assertValid($raw);
            } catch (InvalidArgumentException $e) {
                if ($onInvalid !== null) {
                    $onInvalid($raw, $e->getMessage());
                }
            }
        }

        return $valid;
    }

    /**
     * Validate a Twig namespace identifier (with or without leading `@`). Returns canonical form
     * with leading `@` preserved when supplied. Empty / null is allowed (means "main namespace").
     *
     * @throws InvalidArgumentException
     */
    public static function assertValidNamespace(?string $namespace): ?string
    {
        if ($namespace === null) {
            return null;
        }
        $trimmed = trim($namespace);
        if ($trimmed === '') {
            return null;
        }
        $body = ltrim($trimmed, '@');
        if ($body === '' || preg_match('#^[\w\-]+$#u', $body) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid namespace "%s".', $namespace));
        }

        return str_starts_with($trimmed, '@') ? '@' . $body : $body;
    }
}
