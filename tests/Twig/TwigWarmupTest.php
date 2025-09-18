<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;

/** @internal */
final class TwigWarmupTest extends CIUnitTestCase
{
    private Twig $twig;
    private string $cache;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['url','form','twig_helper']);
        $config = new TwigConfig();
        $config->paths = ['./tests/_support/Templates/'];
        $this->twig = new Twig($config);
        $this->cache = $this->twig->getCachePath();
    }

    public function testWarmupSpecificTemplates()
    {
        $result = $this->twig->warmup(['welcome']);
        $this->assertSame(1, $result['compiled']);
        $this->assertSame(0, $result['errors']);
        $again = $this->twig->warmup(['welcome']);
        $this->assertSame(0, $again['compiled']);
        $this->assertSame(1, $again['skipped']);
    }

    public function testWarmupAll()
    {
        $result = $this->twig->warmupAll();
        $this->assertIsInt($result['compiled']);
        $this->assertIsInt($result['skipped']);
    }

    public function testWarmupForceRecompiles()
    {
        $this->twig->warmup(['welcome']);
        $forced = $this->twig->warmup(['welcome'], true);
        $this->assertSame(1, $forced['compiled']);
    }
}
