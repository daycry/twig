<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;

/** @internal */
final class TwigCachePrefixNormalizationTest extends CIUnitTestCase
{
    private function getResolvedPrefix(string $globalPrefix): string
    {
        $cacheCfg         = config('Cache');
        $cacheCfg->prefix = $globalPrefix; // may include or omit trailing underscore
        $cfg              = new TwigConfig();
        $cfg->paths       = ['./tests/_support/Templates/'];
        $twig             = new Twig($cfg);
        $rp               = (new \ReflectionClass($twig))->getProperty('cachePrefix');
        $rp->setAccessible(true);

        return $rp->getValue($twig);
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
