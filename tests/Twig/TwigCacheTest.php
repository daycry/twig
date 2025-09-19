<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class TwigCacheTest extends CIUnitTestCase
{
    private function listCacheFiles(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }
        $files    = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function testClearCacheRemovesCompiledTemplates()
    {
        $config            = new TwigConfig();
        $config->paths     = []; // use array loader only
        $config->cachePath = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'twig_test';
        $twig              = new Twig($config);
        $twig->withLoader(new ArrayLoader([
            'a.twig' => 'Hello {{ name }}',
        ]));

        // first render (may or may not create physical cache files depending on environment) shouldn't error
        $this->assertSame('Hello CI4', $twig->render('a', ['name' => 'CI4']));
        $cacheDir = $twig->getCachePath();
        if ($cacheDir === '') {
            $this->markTestSkipped('Filesystem cache disabled (CI cache backend auto-detected).');
        }

        // Create a fake compiled file to validate clearCache actually removes files
        $dummyFile = $cacheDir . DIRECTORY_SEPARATOR . 'dummy_compiled.php';
        file_put_contents($dummyFile, '<?php // dummy compiled twig template');
        $this->assertFileExists($dummyFile);

        $removed = $twig->clearCache();
        $this->assertFileDoesNotExist($dummyFile);
        $this->assertGreaterThanOrEqual(1, $removed, 'Expected at least one file removed (dummy)');
    }

    public function testClearCacheWithReinitialize()
    {
        $config            = new TwigConfig();
        $config->paths     = [];
        $config->cachePath = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'twig_test2';
        $twig              = new Twig($config);
        $twig->withLoader(new ArrayLoader([
            'b.twig' => 'Value {{ v }}',
        ]));
        $twig->render('b', ['v' => 1]);
        $initialTwig = $twig->getTwig();

        if ($twig->getCachePath() === '') {
            $this->markTestSkipped('Filesystem cache disabled (CI cache backend auto-detected).');
        }

        $twig->clearCache(true); // should reset environment
        $this->assertNotSame($initialTwig, $twig->getTwig(), 'Twig environment should be recreated after reinitialize');
    }
}
