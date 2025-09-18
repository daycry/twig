<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/** @internal */
final class TwigWarmupTest extends CIUnitTestCase
{
    private Twig $twig;
    private string $cache;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['url', 'form', 'twig_helper']);
        $config        = new TwigConfig();
        $config->paths = ['./tests/_support/Templates/'];
        // Ensure cache path is explicitly set so warmup compiles into a known directory
        $config->cachePath = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'twig_warmup_test';
        // Clean previous cache to guarantee deterministic compiled/skipped counts
        if (is_dir($config->cachePath)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($config->cachePath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($it as $file) {
                /** @var SplFileInfo $file */
                if ($file->isFile()) {
                    @unlink($file->getPathname());
                }
            }
        }
        $this->twig  = new Twig($config);
        $this->cache = $this->twig->getCachePath();
    }

    public function testWarmupSpecificTemplates()
    {
        $result = $this->twig->warmup(['welcome']);
        $this->assertSame(1, $result['compiled']);
        $this->assertSame(0, $result['errors']);
        $again = $this->twig->warmup(['welcome']);
        $this->assertSame(0, $again['compiled']);
        $this->assertSame(1, $again['skipped']);
    }

    public function testWarmupAll()
    {
        $result = $this->twig->warmupAll();
        $this->assertIsInt($result['compiled']);
        $this->assertIsInt($result['skipped']);
    }

    public function testWarmupForceRecompiles()
    {
        $this->twig->warmup(['welcome']);
        $forced = $this->twig->warmup(['welcome'], true);
        $this->assertSame(1, $forced['compiled']);
    }
}
