<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class TwigInvalidateTemplateTest extends CIUnitTestCase
{
    public function testInvalidateTemplateRemovesCompiledFile()
    {
        $config = new TwigConfig();
        $config->paths = [];
        $config->cachePath = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'twig_invalidate';
        $twig = new Twig($config);
        $twig->withLoader(new ArrayLoader([
            'temp.twig' => 'Value {{ v }}'
        ]));

        // First render to (potentially) create compiled file
        $this->assertSame('Value 1', $twig->render('temp', ['v' => 1]));
        $cacheDir = $twig->getCachePath();

        // Simulate absence of an actual compiled file by creating a fake one matching pattern
        $hash = md5('temp.twig');
        $dummy = $cacheDir . DIRECTORY_SEPARATOR . $hash . '.php';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        file_put_contents($dummy, '<?php // compiled');
        $this->assertFileExists($dummy);

        $removed = $twig->invalidateTemplate('temp');
        $this->assertGreaterThanOrEqual(1, $removed);
        $this->assertFileDoesNotExist($dummy);

        // Re-render should still work (will recompile or use in-memory class)
        $this->assertSame('Value 2', $twig->render('temp', ['v' => 2]));
    }
}
