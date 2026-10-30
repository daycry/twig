<?php

declare(strict_types=1);

namespace Tests\Twig;

use Config\Services;
use Daycry\Twig\CodeIgniter4\Config\Twig as TwigConfig;
use Daycry\Twig\CodeIgniter4\Twig;
use Tests\Support\ArrayCache;
use Tests\Support\TestCase as CIUnitTestCase;

/**
 * @internal
 */
final class TwigCachePrefixNormalizationTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Services::injectMock('cache', new ArrayCache());
    }

    private function getResolvedPrefix(string $globalPrefix): string
    {
        $cacheCfg         = config('Cache');
        $cacheCfg->prefix = $globalPrefix; // may include or omit trailing underscore
        $cfg              = new TwigConfig();
        $cfg->paths       = ['./tests/_support/Templates/'];
        $twig             = new Twig($cfg);

        return $twig->getDiagnostics()['cache']['prefix'];
    }

    public function testEmptyGlobal(): void
    {
        $this->assertSame('twig_', $this->getResolvedPrefix(''));
    }

    public function testGlobalWithoutUnderscore(): void
    {
        $this->assertSame('twig_', $this->getResolvedPrefix('app'));
    }

    public function testGlobalWithUnderscore(): void
    {
        $this->assertSame('_twig_', $this->getResolvedPrefix('app_'));
    }

    public function testMediaprous(): void
    {
        $this->assertSame('_twig_', $this->getResolvedPrefix('mediaprous_'));
    }
}
