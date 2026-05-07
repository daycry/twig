<?php

namespace Tests\Support\Traits;

use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Twig\Loader\ArrayLoader;

/**
 * Reusable setup helpers for Twig tests. Goals:
 *  - cut down on the duplicated `helper(['url','form','twig_helper'])` /
 *    `paths = ['./tests/_support/Templates/']` boilerplate;
 *  - make in-memory templates (ArrayLoader) the default for tests that don't
 *    actually need filesystem behavior, eliminating cross-test bleed via
 *    leftover compiled cache files.
 */
trait TwigTestSetup
{
    /**
     * Build a Twig instance pointing at the bundled test templates folder.
     */
    protected function setupTwigWithTemplates(?string $cachePath = null, ?callable $configure = null): Twig
    {
        helper(['url', 'form', 'twig_helper']);
        $config        = new TwigConfig();
        $config->paths = ['./tests/_support/Templates/'];
        if ($cachePath !== null) {
            $config->cachePath = $cachePath;
            $this->cleanCacheDir($cachePath);
        }
        if ($configure !== null) {
            $configure($config);
        }

        return new Twig($config);
    }

    /**
     * Build a Twig instance backed by an in-memory ArrayLoader so the test does
     * not depend on filesystem layout or compiled cache state.
     *
     * @param array<string,string> $templates name => source code
     */
    protected function setupTwigWithInMemory(array $templates, ?callable $configure = null): Twig
    {
        helper(['url', 'form', 'twig_helper']);
        $config = new TwigConfig();
        if ($configure !== null) {
            $configure($config);
        }
        $twig = new Twig($config);
        $twig->withLoader(new ArrayLoader($templates));

        return $twig;
    }

    /**
     * Wipe a cache directory's compiled artifacts and JSON sidecar files. Used
     * in setUp/tearDown to keep tests isolated.
     */
    protected function cleanCacheDir(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($it as $entry) {
            if ($entry->isFile()) {
                @unlink($entry->getPathname());
            } elseif ($entry->isDir()) {
                @rmdir($entry->getPathname());
            }
        }
    }
}
