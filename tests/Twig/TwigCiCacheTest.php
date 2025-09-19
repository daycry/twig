<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;

/** @internal */
final class TwigCiCacheTest extends CIUnitTestCase
{
    public function testCiCacheBackendRendersAndReuses()
    {
        $config        = new TwigConfig();
        $config->paths = ['./tests/_support/Templates/'];
        // cacheBackend deprecated/ignored; auto-detection should still pick CI service
        $config->cachePrefix = 'twig_test_';
        $twig                = new Twig($config);

        // First render (compilation)
        $out1 = $twig->render('welcome', ['name' => 'CI']);
        $this->assertSame("Hello CI!\n", $out1);
        $diag1 = $twig->getDiagnostics();

        // Second render should reuse compiled template (still same output)
        $out2 = $twig->render('welcome', ['name' => 'CI']);
        $this->assertSame($out1, $out2);
        $diag2 = $twig->getDiagnostics();

        $this->assertSame($diag1['cache']['mode'], $diag2['cache']['mode']);
        $this->assertSame('service', $diag2['cache']['mode']);
        $this->assertNotEmpty($diag2['cache']['service_class']);
    }
}
