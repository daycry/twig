<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Twig;

final class TwigUnregisterTest extends CIUnitTestCase
{
    public function testUnregisterFunction(): void
    {
        $cfg = new \Daycry\Twig\Config\Twig();
        $cfg->paths = [__DIR__.'/../_support/Templates'];
        $twig = new Twig($cfg);
        $called = 0;
        $twig->registerFunction('temp_fn', function() use (&$called) { $called++; return 'X'; });
        // Render template via createTemplate to trigger addition
        $twig->getTwig(); // force environment creation
        $html = $twig->createTemplate("{{ temp_fn() }}");
        $this->assertSame('X', trim($html));
        $this->assertTrue($twig->unregisterFunction('temp_fn'));
        // Re-create environment and ensure function no longer exists
        $twig->getTwig();
        $this->expectException(\Twig\Error\RuntimeError::class);
        $twig->createTemplate("{{ temp_fn() }}");
    }

    public function testUnregisterFilter(): void
    {
    $cfg = new \Daycry\Twig\Config\Twig();
    $cfg->paths = [__DIR__.'/../_support/Templates'];
    $twig = new Twig($cfg);
        $twig->registerFilter('brackets', function($s){ return '['.$s.']'; });
        $twig->getTwig();
        $out = $twig->createTemplate("{{ 'ok'|brackets }}");
        $this->assertSame('[ok]', trim($out));
        $this->assertTrue($twig->unregisterFilter('brackets'));
        $twig->getTwig();
        $this->expectException(\Twig\Error\RuntimeError::class);
        $twig->createTemplate("{{ 'ok'|brackets }}");
    }

    public function testUnregisterExtension(): void
    {
    $cfg = new \Daycry\Twig\Config\Twig();
    $cfg->paths = [__DIR__.'/../_support/Templates'];
    $twig = new Twig($cfg);
        // use core debug extension dynamically as test (not in default list when production?)
        $this->assertTrue($twig->registerExtension(\Twig\Extension\StringLoaderExtension::class) instanceof Twig);
        $twig->getTwig();
        // Now attempt to unregister (should rebuild environment)
        $this->assertTrue($twig->unregisterExtension(\Twig\Extension\StringLoaderExtension::class));
        $twig->getTwig();
        // Extension should be gone; registering again should succeed without exception
        $twig->registerExtension(\Twig\Extension\StringLoaderExtension::class);
        $this->assertTrue(true);
    }
}
