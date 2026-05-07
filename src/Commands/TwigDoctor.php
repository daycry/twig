<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\CLI;
use Throwable;
use Twig\Environment as TwigEnvironment;

/**
 * `php spark twig:doctor` — health check that surfaces common misconfigurations
 * (missing template paths, unwritable cache directory, prefix collisions,
 * APCu/snapshot mismatches) before they become runtime failures in production.
 *
 * Exit code reflects the worst severity found: 0 OK, 1 ERROR, anything in
 * between is reported but does not fail the run.
 */
class TwigDoctor extends AbstractTwigCommand
{
    private const SEV_OK    = 'ok';
    private const SEV_WARN  = 'warning';
    private const SEV_ERROR = 'error';

    protected $name        = 'twig:doctor';
    protected $description = 'Run a health check on the Twig integration and report problems.';
    protected $usage       = 'twig:doctor [--json]';
    protected $options     = [
        '--json' => 'Emit JSON result.',
    ];

    public function run(array $params)
    {
        $asJson = $this->flag('json', $params);
        $facade = $this->twig();
        if ($facade === null) {
            return EXIT_ERROR;
        }

        $checks = [];

        // Twig Environment available
        try {
            $env      = $facade->getTwig();
            $checks[] = $this->ok('environment', sprintf('Twig %s loaded', TwigEnvironment::VERSION));
        } catch (Throwable $e) {
            $checks[] = $this->fail('environment', 'Twig environment failed to initialize: ' . $e->getMessage());

            return $this->finalize($checks, $asJson);
        }

        // Configured paths
        $paths = $facade->getPaths();
        if ($paths === []) {
            $checks[] = $this->warn('paths', 'No template paths configured');
        } else {
            foreach ($paths as $entry) {
                $path = is_array($entry) ? ($entry[0] ?? '') : (string) $entry;
                if (! is_string($path) || $path === '') {
                    $checks[] = $this->fail('paths', 'Invalid path entry: ' . var_export($entry, true));

                    continue;
                }
                if (! is_dir($path)) {
                    $checks[] = $this->fail('paths', sprintf('Path does not exist: %s', $path));

                    continue;
                }
                if (! is_readable($path)) {
                    $checks[] = $this->fail('paths', sprintf('Path is not readable: %s', $path));

                    continue;
                }
                $checks[] = $this->ok('paths', sprintf('Path OK: %s', $path));
            }
        }

        // Cache directory writability (only when filesystem mode)
        $cachePath = $facade->getCachePath();
        if ($cachePath !== '') {
            if (! is_dir($cachePath)) {
                $checks[] = $this->warn('cache', sprintf('Cache dir missing: %s (will be created on first compile)', $cachePath));
            } elseif (! is_writable($cachePath)) {
                $checks[] = $this->fail('cache', sprintf('Cache dir not writable: %s', $cachePath));
            } else {
                $checks[] = $this->ok('cache', sprintf('Cache dir writable: %s', $cachePath));
            }
        } else {
            $checks[] = $this->ok('cache', 'Cache backed by service (filesystem path not used)');
        }

        // Diagnostics consistency
        try {
            $diag     = $facade->getDiagnostics();
            $checks[] = $this->ok('diagnostics', sprintf('Diagnostics OK (renders=%d)', $diag['renders'] ?? 0));
            if (! empty($diag['cache']['reconstructed_index'])) {
                $checks[] = $this->warn('diagnostics', 'Compile index was reconstructed from filesystem; run twig:warmup --all to rebuild it.');
            }
        } catch (Throwable $e) {
            $checks[] = $this->fail('diagnostics', 'getDiagnostics() failed: ' . $e->getMessage());
        }

        // APCu availability when capabilities ask for snapshot
        if (function_exists('apcu_enabled')) {
            $apcu = @apcu_enabled();
            if ($apcu) {
                $checks[] = $this->ok('apcu', 'APCu enabled');
            } else {
                $checks[] = $this->warn('apcu', 'APCu extension installed but not enabled (apc.enabled / apc.enable_cli)');
            }
        } else {
            $checks[] = $this->warn('apcu', 'APCu extension not installed (snapshot acceleration disabled)');
        }

        return $this->finalize($checks, $asJson);
    }

    /**
     * @param array<int,array{severity:string,scope:string,message:string}> $checks
     */
    private function finalize(array $checks, bool $asJson): int
    {
        $hasError = false;

        foreach ($checks as $c) {
            if ($c['severity'] === self::SEV_ERROR) {
                $hasError = true;
                break;
            }
        }

        if ($asJson) {
            return $this->writeJson(! $hasError, ['checks' => $checks]);
        }

        $colors = [self::SEV_OK => 'green', self::SEV_WARN => 'yellow', self::SEV_ERROR => 'red'];

        foreach ($checks as $c) {
            $tag = strtoupper($c['severity']);
            CLI::write(sprintf('[%s] %s — %s', $tag, $c['scope'], $c['message']), $colors[$c['severity']] ?? 'white');
        }

        return $hasError ? EXIT_ERROR : EXIT_SUCCESS;
    }

    /**
     * @return array{severity:string,scope:string,message:string}
     */
    private function ok(string $scope, string $message): array
    {
        return ['severity' => self::SEV_OK, 'scope' => $scope, 'message' => $message];
    }

    /**
     * @return array{severity:string,scope:string,message:string}
     */
    private function warn(string $scope, string $message): array
    {
        return ['severity' => self::SEV_WARN, 'scope' => $scope, 'message' => $message];
    }

    /**
     * @return array{severity:string,scope:string,message:string}
     */
    private function fail(string $scope, string $message): array
    {
        return ['severity' => self::SEV_ERROR, 'scope' => $scope, 'message' => $message];
    }
}
