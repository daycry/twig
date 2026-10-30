<?php

declare(strict_types=1);

namespace Tests\Collectors;

use Daycry\Twig\CodeIgniter4\Config\Services;
use Daycry\Twig\CodeIgniter4\Debug\Toolbar\Collectors\Twig;
use Tests\Support\TestCase;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class TwigTest extends TestCase
{
    public function testBadgeTracksRenderedViews(): void
    {
        Services::resetSingle('twig');
        $twig = Services::twig();
        $twig->withLoader(new ArrayLoader(['badge.twig' => 'badge']));
        $twig->render('badge');
        $this->assertSame(1, (new Twig())->getBadgeValue());
    }

    public function testTabContentRenders(): void
    {
        $this->assertStringContainsString('Twig Diagnostics', (new Twig())->tabContent());
    }

    public function testTwigViewPartialExists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/src/Adapter/CodeIgniter4/Debug/Toolbar/Views/_twig.tpl');
    }

    public function testDiagnosticsStructure(): void
    {
        $diagnostics = Services::twig()->getDiagnostics();

        foreach (['renders', 'cache', 'discovery', 'performance'] as $key) {
            $this->assertArrayHasKey($key, $diagnostics);
        }
    }
}
