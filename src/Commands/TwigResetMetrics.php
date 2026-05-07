<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\CLI;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Resets Twig diagnostic artifacts: discovery stats, warmup summary, optionally compile index & cache.
 */
class TwigResetMetrics extends AbstractTwigCommand
{
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
        $incIndex = $this->flag('include-index', $params);
        $incCache = $this->flag('include-cache', $params);
        $asJson   = $this->flag('json', $params);

        $twig = $this->twig();
        if ($twig === null) {
            return EXIT_ERROR;
        }
        $cacheDir       = rtrim($twig->getCachePath(), DIRECTORY_SEPARATOR);
        $discoveryStats = $cacheDir . DIRECTORY_SEPARATOR . 'discovery-stats.json';
        $warmupSummary  = $cacheDir . DIRECTORY_SEPARATOR . 'warmup-summary.json';
        $listSnapshot   = $cacheDir . DIRECTORY_SEPARATOR . 'discovery-stats-list.json';
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
            } catch (Throwable) {
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
                    if (preg_match('/^(discovery-stats|warmup-summary|compile-index)/', $name)) {
                        continue;
                    }
                    if (str_ends_with($name, '.json')) {
                        continue;
                    }
                    if (@unlink($file->getPathname())) {
                        $cacheFilesRemoved++;
                    }
                }
            }
        }

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

            return empty($fail) ? EXIT_SUCCESS : EXIT_ERROR;
        }

        if ($removed === [] && $cacheFilesRemoved === 0) {
            CLI::write('Nothing to reset in: ' . $cacheDir, 'yellow');

            return EXIT_SUCCESS;
        }
        if ($removed !== []) {
            CLI::write('Removed metadata: ' . count($removed) . ' file(s).', 'green');
        }
        if ($cacheFilesRemoved > 0) {
            CLI::write('Removed compiled cache files: ' . $cacheFilesRemoved, 'green');
        }
        if ($fail !== []) {
            CLI::write('Failed to remove: ' . implode(', ', $fail), 'red');

            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }
}
