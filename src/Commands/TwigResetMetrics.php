<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Twig\Config\Services;
use Daycry\Twig\Twig;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Resets Twig diagnostic artifacts: discovery stats, warmup summary, optionally compile index & cache.
 */
class TwigResetMetrics extends BaseCommand
{
    protected $group       = 'Twig';
    protected $name        = 'twig:reset-metrics';
    protected $description = 'Resets discovery stats, warmup summary, and optionally compile index/cache.';
    protected $usage       = 'twig:reset-metrics [--include-index] [--include-cache] [--json]';
    protected $options     = [
        '--include-index' => 'Also delete compile-index.json (forget which templates were compiled).',
        '--include-cache' => 'Also delete compiled template cache files (same as clear-cache).',
        '--json'          => 'Emit JSON output.',
    ];

    public function run(array $params)
    {
        $incIndex = in_array('--include-index', $params, true) || CLI::getOption('include-index');
        $incCache = in_array('--include-cache', $params, true) || CLI::getOption('include-cache');
        $asJson   = in_array('--json', $params, true)          || CLI::getOption('json');

        /** @var Twig $twig */
        $twig           = Services::twig();
        $cacheDir       = rtrim($twig->getCachePath(), DIRECTORY_SEPARATOR);
        $discoveryStats = $cacheDir . DIRECTORY_SEPARATOR . 'discovery-stats.json';
        $warmupSummary  = $cacheDir . DIRECTORY_SEPARATOR . 'warmup-summary.json';
        $listSnapshot   = $cacheDir . DIRECTORY_SEPARATOR . 'discovery-stats-list.json'; // possible pattern
        $compileIndex   = $cacheDir . DIRECTORY_SEPARATOR . 'compile-index.json';

        $removed   = [];
        $fail      = [];
        $tryDelete = static function (string $path) use (&$removed, &$fail) {
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
                /** @var SplFileInfo $file */
                if ($file->isFile()) {
                    $name = $file->getFilename();
                    // skip our marker jsons already handled
                    if (preg_match('/^(discovery-stats|warmup-summary|compile-index)/', $name)) {
                        continue;
                    }
                    if (substr($name, -5) === '.json') {
                        continue;
                    }
                    if (@unlink($file->getPathname())) {
                        $cacheFilesRemoved++;
                    }
                }
            }
        }

        // Reset in-process metrics (best-effort)
        // Force a fresh discovery object and warmup memory to avoid showing stale toolbar after command
        // (Simplest: reinstantiate service)
        // Nothing more needed here; next request will lazily reconstruct stats.

        if ($asJson) {
            $payload = [
                'removed_files'       => $removed,
                'failed'              => $fail,
                'cache_files_removed' => $cacheFilesRemoved,
                'include_index'       => $incIndex,
                'include_cache'       => $incCache,
                'ok'                  => empty($fail),
            ];
            CLI::write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

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
