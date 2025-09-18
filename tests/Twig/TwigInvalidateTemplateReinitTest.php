<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class TwigInvalidateTemplateReinitTest extends CIUnitTestCase
{
    public function testInvalidateTemplateWithReinitializeReflectsSourceChange()
    {
        $config = new TwigConfig();
        $config->paths = [];
        $config->cachePath = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'twig_invalidate_reinit';
        $loader = new ArrayLoader([
            'sample.twig' => 'Version A'
        ]);
        $twig = new Twig($config);
        $twig->withLoader($loader);

        $this->assertSame('Version A', $twig->render('sample'));

        // Change template source (ArrayLoader updated dynamically)
        $loader->setTemplate('sample.twig', 'Version B');

        // Without invalidation, depending on caching/in-memory class, might still return A
        $outBefore = $twig->render('sample');

        // Invalidate with reinitialize to force recompilation
        $twig->invalidateTemplate('sample', true);
        $outAfter = $twig->render('sample');

        // If caching prevented update before, after reinit we must have new content
        $this->assertSame('Version B', $outAfter);
    }
}
