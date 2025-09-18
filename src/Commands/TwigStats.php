<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Twig\Config\Services;
use Daycry\Twig\Twig;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TwigStats extends BaseCommand
{
    protected $group       = 'Twig';
    protected $name        = 'twig:stats';
    protected $description = 'Shows statistics about Twig templates and cache usage.';
    protected $usage       = 'twig:stats';

    public function run(array $params)
    {
        /** @var Twig $twig */
        $twig     = Services::twig();
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
