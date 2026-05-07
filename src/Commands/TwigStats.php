<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\CLI;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TwigStats extends AbstractTwigCommand
{
    protected $name        = 'twig:stats';
    protected $description = 'Shows statistics about Twig templates and cache usage.';
    protected $usage       = 'twig:stats';

    public function run(array $params)
    {
        $twig = $this->twig();
        if ($twig === null) {
            return EXIT_ERROR;
        }
        $all      = $twig->listTemplates(true);
        $total    = count($all);
        $compiled = 0;

        foreach ($all as $row) {
            if ($row['compiled']) {
                $compiled++;
            }
        }
        $cachePath    = $twig->getCachePath();
        $cacheEnabled = $twig->isCacheEnabled() ? 'yes' : 'no';
        $cacheFiles   = $this->countFiles($cachePath);
        CLI::write('Templates total: ' . $total);
        CLI::write('Templates compiled (index): ' . $compiled);
        CLI::write('Cache enabled: ' . $cacheEnabled);
        CLI::write('Cache path: ' . $cachePath);
        CLI::write('Cache files present: ' . $cacheFiles);

        return EXIT_SUCCESS;
    }

    private function countFiles(string $path): int
    {
        if ($path === '' || ! is_dir($path)) {
            return 0;
        }
        $c  = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

        foreach ($it as $f) {
            if ($f->isFile()) {
                $c++;
            }
        }

        return $c;
    }
}
