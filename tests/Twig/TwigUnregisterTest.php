<?php

declare(strict_types=1);

namespace Tests\Twig;

use Daycry\Twig\CodeIgniter4\Twig;
use Tests\Support\TestCase as CIUnitTestCase;
use Twig\Error\SyntaxError;
use Twig\Extension\StringLoaderExtension;

/**
 * @internal
 */
final class TwigUnregisterTest extends CIUnitTestCase
{
    public function testUnregisterFunction(): void
    {
        $cfg        = new \Daycry\Twig\CodeIgniter4\Config\Twig();
        $cfg->paths = [__DIR__ . '/../_support/Templates'];
        $twig       = new Twig($cfg);
        $called     = 0;
        $twig->registerFunction('temp_fn', static function () use (&$called) {
            $called++;

            return 'X';
        });
        // Render template via createTemplate to trigger addition
        $twig->getTwig(); // force environment creation
        $html = $twig->createTemplate('{{ temp_fn() }}');
        $this->assertSame('X', trim((string) $html));
        $this->assertTrue($twig->unregisterFunction('temp_fn'));
        // Re-create environment and ensure function no longer exists
        $twig->getTwig();
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Unknown "temp_fn" function');
        $twig->createTemplate('{{ temp_fn() }}');
    }

    public function testUnregisterFilter(): void
    {
        $cfg        = new \Daycry\Twig\CodeIgniter4\Config\Twig();
        $cfg->paths = [__DIR__ . '/../_support/Templates'];
        $twig       = new Twig($cfg);
        $twig->registerFilter('brackets', static fn ($s) => '[' . $s . ']');
        $twig->getTwig();
        $out = $twig->createTemplate("{{ 'ok'|brackets }}");
        $this->assertSame('[ok]', trim((string) $out));
        $this->assertTrue($twig->unregisterFilter('brackets'));
        $twig->getTwig();
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Unknown "brackets" filter');
        $twig->createTemplate("{{ 'ok'|brackets }}");
    }

    public function testUnregisterExtension(): void
    {
        $cfg        = new \Daycry\Twig\CodeIgniter4\Config\Twig();
        $cfg->paths = [__DIR__ . '/../_support/Templates'];
        $twig       = new Twig($cfg);
        // use core debug extension dynamically as test (not in default list when production?)
        $this->assertInstanceOf(Twig::class, $twig->registerExtension(StringLoaderExtension::class));
        $twig->getTwig();
        // Now attempt to unregister (should rebuild environment)
        $this->assertTrue($twig->unregisterExtension(StringLoaderExtension::class));
        $twig->getTwig();
        // Extension should be gone; registering again should succeed without exception
        $twig->registerExtension(StringLoaderExtension::class);
        $this->assertTrue($twig->getTwig()->hasExtension(StringLoaderExtension::class));
    }
}
