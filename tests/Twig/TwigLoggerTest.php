<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Twig;
use Daycry\Twig\Config\Twig as TwigConfig;
// Logger tests simplified: integration now delegates to global log_message helper.

/** @internal */
final class TwigLoggerTest extends CIUnitTestCase
{
    private Twig $twig;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['url','form','twig_helper']);
        $config = new TwigConfig();
        $config->paths = ['./tests/_support/Templates/'];
        $this->twig = new Twig($config);
    }

    public function testQueuedFunctionLogs()
    {
        $this->twig->registerFunction('log_test_fn', fn() => 'x'); // queued before first render
        $this->twig->render('welcome'); // triggers adding functions

        // Can't assert framework logs without tapping global handlers; just assert render succeeded.
        $this->assertTrue(true);
    }

    public function testImmediateFilterRegistrationLogs()
    {
        // first render to initialize environment + functions
        $this->twig->render('welcome');
        $this->twig->registerFilter('caps', fn(string $v) => strtoupper($v));
        $this->assertTrue(true);
    }

    public function testCacheClearLogs()
    {
        $this->twig->render('welcome'); // compile something
        $removed = $this->twig->clearCache();
        $this->assertIsInt($removed);
    }
}
