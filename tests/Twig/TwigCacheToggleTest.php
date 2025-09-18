<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Twig;

final class TwigCacheToggleTest extends CIUnitTestCase
{
    public function testDisableEnableCache(): void
    {
        $cfg = new \Daycry\Twig\Config\Twig();
        $cfg->paths = [__DIR__.'/../_support/Templates'];
        $twig = new Twig($cfg);
        $twig->warmup(['welcome']); // compile something
        $path = $twig->getCachePath();
        $filesBefore = $this->countTwigCacheFiles($path);
        $this->assertGreaterThan(0, $filesBefore);

        $twig->disableCache();
        $this->assertFalse($twig->isCacheEnabled());
        // Rendering should not create new compiled files
        $twig->render('welcome', []);
        $filesAfterDisable = $this->countTwigCacheFiles($path);
        $this->assertSame($filesBefore, $filesAfterDisable, 'Cache files should not grow when disabled');

        $twig->enableCache($path); // re-enable existing path
        $this->assertTrue($twig->isCacheEnabled());
        $twig->render('welcome', ['x' => 1]);
        $filesAfterEnable = $this->countTwigCacheFiles($path);
        $this->assertGreaterThanOrEqual($filesBefore, $filesAfterEnable);
    }

    private function countTwigCacheFiles(string $path): int
    {
        if ($path === '' || !is_dir($path)) { return 0; }
        $c = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) { if ($f->isFile()) { $c++; } }
        return $c;
    }
}
