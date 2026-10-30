<?php

declare(strict_types=1);

namespace Tests\Twig;

use Daycry\Twig\CodeIgniter4\Config\Twig as TwigConfig;
use Daycry\Twig\CodeIgniter4\Twig;
use Tests\Support\TestCase as CIUnitTestCase;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class TwigInvalidateTemplateTest extends CIUnitTestCase
{
    public function testInvalidateTemplateRemovesCompiledFile()
    {
        $config            = new TwigConfig();
        $config->paths     = [];
        $config->cachePath = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'twig_invalidate';
        $twig              = new Twig($config);
        $twig->withLoader(new ArrayLoader([
            'temp.twig' => 'Value {{ v }}',
        ]));

        // First render to (potentially) create compiled file
        $this->assertSame('Value 1', $twig->render('temp', ['v' => 1]));
        $cacheDir = $twig->getCachePath();
        if ($cacheDir === '') {
            $this->markTestSkipped('Filesystem cache disabled (CI cache backend auto-detected).');
        }

        $environment = $twig->getTwig();
        $dummy       = $environment->getCache(false)->generateKey('temp.twig', $environment->getTemplateClass('temp.twig'));
        $this->assertFileExists($dummy);

        $removed = $twig->invalidateTemplate('temp');
        $this->assertGreaterThanOrEqual(1, $removed);
        $this->assertFileDoesNotExist($dummy);

        // Re-render should still work (will recompile or use in-memory class)
        $this->assertSame('Value 2', $twig->render('temp', ['v' => 2]));
    }
}
