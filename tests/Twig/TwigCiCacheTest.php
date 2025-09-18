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
        $config               = new TwigConfig();
        $config->paths        = ['./tests/_support/Templates/'];
        $config->cacheBackend = 'ci';
        $config->cachePrefix  = 'twig_test_';
        $twig                 = new Twig($config);

        // First render (compilation)
        $out1 = $twig->render('welcome', ['name' => 'CI']);
        $this->assertSame("Hello CI!\n", $out1);
        $diag1 = $twig->getDiagnostics();

        // Second render should reuse compiled template (still same output)
        $out2 = $twig->render('welcome', ['name' => 'CI']);
        $this->assertSame($out1, $out2);
        $diag2 = $twig->getDiagnostics();

        $this->assertSame($diag1['cache']['backend'], $diag2['cache']['backend']);
        $this->assertStringStartsWith('ci:', $diag2['cache']['backend']);
    }
}
