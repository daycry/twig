<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Twig\Config\Services;
use Daycry\Twig\Twig;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Alias for twig:reset-metrics to provide a shorter command.
 */
class TwigReset extends BaseCommand
{
    protected $group       = 'Twig';
    protected $name        = 'twig:reset';
    protected $description = 'Resets discovery stats, warmup summary and optionally compile index/cache.';
    protected $usage       = 'twig:reset [--include-index] [--include-cache] [--json]';
    protected $options     = [
        '--include-index' => 'Also delete compile-index.json',
        '--include-cache' => 'Also delete compiled template cache',
        '--json'          => 'Emit JSON output.',
    ];

    public function run(array $params)
    {
        // Just forward to the main command
        // Build command string: reuse params as-is
        $forward = 'twig:reset-metrics';

        foreach ($params as $p) {
            $forward .= ' ' . escapeshellarg($p);
        }
        // Re-implement minimal forwarding logic to avoid constructor signature issues.
        $incIndex = in_array('--include-index', $params, true) || CLI::getOption('include-index');
        $incCache = in_array('--include-cache', $params, true) || CLI::getOption('include-cache');
        $asJson   = in_array('--json', $params, true)          || CLI::getOption('json');
        /** @var Twig $twig */
        $twig           = Services::twig();
        $cacheDir       = rtrim($twig->getCachePath(), DIRECTORY_SEPARATOR);
        $discoveryStats = $cacheDir . DIRECTORY_SEPARATOR . 'discovery-stats.json';
        $warmupSummary  = $cacheDir . DIRECTORY_SEPARATOR . 'warmup-summary.json';
        $listSnapshot   = $cacheDir . DIRECTORY_SEPARATOR . 'discovery-stats-list.json';
        $compileIndex   = $cacheDir . DIRECTORY_SEPARATOR . 'compile-index.json';
        $removed        = [];
        $fail           = [];
        $tryDelete      = static function (string $path) use (&$removed, &$fail) {
            if (! is_file($path)) {
                return;
            }

try {
                @unlink($path);
                $removed[] = $path;
            } catch (Throwable $e) {
                $fail[] = $path;
            }
        };
        $tryDelete($discoveryStats);
        $tryDelete($warmupSummary);
        $tryDelete($listSnapshot);
        if ($incIndex) {
            $tryDelete($compileIndex);
        }
        $cacheFilesRemoved = 0;
        if ($incCache) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

            foreach ($it as $file) {
                if ($file->isFile()) {
                    $name = $file->getFilename();
                    if (preg_match('/^(discovery-stats|warmup-summary|compile-index)/', $name)) {
                        continue;
                    } if (substr($name, -5) === '.json') {
                        continue;
                    } if (@unlink($file->getPathname())) {
                        $cacheFilesRemoved++;
                    }
                }
            }
        }
        if ($asJson) {
            CLI::write(json_encode([
                'removed_files'       => $removed,
                'failed'              => $fail,
                'cache_files_removed' => $cacheFilesRemoved,
                'include_index'       => $incIndex,
                'include_cache'       => $incCache,
                'ok'                  => empty($fail),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return;
        }
        if ($removed === [] && $cacheFilesRemoved === 0) {
            CLI::write('Nothing to reset in: ' . $cacheDir, 'yellow');

            return;
        }
        if ($removed !== []) {
            CLI::write('Removed metadata: ' . count($removed) . ' file(s).', 'green');
        }
        if ($cacheFilesRemoved > 0) {
            CLI::write('Removed compiled cache files: ' . $cacheFilesRemoved, 'green');
        }
        if ($fail !== []) {
            CLI::write('Failed to remove: ' . implode(', ', $fail), 'red');
        }
    }
}
