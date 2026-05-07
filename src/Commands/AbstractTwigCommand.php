<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Twig\Config\Services;
use Daycry\Twig\Twig;
use Throwable;

/**
 * Shared scaffolding for every `twig:*` CLI command:
 *  - declares the common command group;
 *  - resolves the Twig service with a unified failure path so a misconfigured
 *    application never reaches a fatal error in `Services::twig()` (the user
 *    just gets an error message and a non-zero exit code);
 *  - parses CLI flags consistently (`--reinit`, `--force`, `--json`, `--quiet`,
 *    `--verbose`) so individual commands stay focused on their actual logic.
 */
abstract class AbstractTwigCommand extends BaseCommand
{
    protected $group = 'Twig';

    /**
     * Resolve the Twig service. Returns null when resolution fails after
     * emitting a CLI error. Subclasses should `return EXIT_ERROR;` on null.
     */
    protected function twig(): ?Twig
    {
        try {
            return Services::twig();
        } catch (Throwable $e) {
            CLI::error('Twig service is unavailable: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Returns the positional arguments after stripping all `--*` flags. Used by
     * commands that accept a variable list of templates.
     *
     * @param list<string> $params
     *
     * @return list<string>
     */
    protected function positional(array $params): array
    {
        return array_values(array_filter($params, static fn (string $p): bool => ! str_starts_with($p, '--')));
    }

    /**
     * Whether a boolean flag is set, accepting both inline `--flag` and the
     * normalized form via `CLI::getOption()`.
     */
    protected function flag(string $name, array $params): bool
    {
        return in_array('--' . $name, $params, true) || (bool) CLI::getOption($name);
    }

    /**
     * Emit a JSON payload to stdout in a uniform shape:
     *   { "ok": bool, "data": ..., "error": ?string }
     * Returns the exit code derived from the `ok` flag so commands can simply
     * `return $this->writeJson(...);` to finish.
     */
    protected function writeJson(bool $ok, mixed $data = null, ?string $error = null): int
    {
        $payload = ['ok' => $ok, 'data' => $data, 'error' => $error];
        CLI::write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $ok ? EXIT_SUCCESS : EXIT_ERROR;
    }
}
