<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Collectors;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Twig\Config\Services;
use Daycry\Twig\Debug\Toolbar\Collectors\Twig;

/**
 * @internal
 */
final class TwigTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace;
    protected $refresh = true;
    private Twig $collector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collector = new Twig();
    }

    public function testCollect()
    {
        $this->assertInstanceOf(Twig::class, $this->collector);
    }

    public function testTabContentRenders()
    {
        $html = $this->collector->tabContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('Twig Diagnostics', $html);
    }

    public function testTwigViewPartialExists()
    {
        $path = realpath(__DIR__ . '/../../src/Debug/Toolbar/Views/_twig.tpl.php');
        // When running from vendor or source tree, adjust if needed
        if ($path === false) {
            $this->markTestSkipped('Twig toolbar partial not found in expected relative location.');

            return;
        }
        $this->assertFileExists($path);
        ob_start();
        $vars = ['Twig Diagnostics' => []];
        include $path; // should not throw
        $out = ob_get_clean();
        $this->assertIsString($out);
    }

    public function testDiagnosticsStructure()
    {
        // Access service instance to ensure Twig environment initializes
        $service = Services::twig();
        if (method_exists($service, 'getDiagnostics')) {
            $diag = $service->getDiagnostics();
            $this->assertIsArray($diag);
            $this->assertArrayHasKey('renders', $diag);
            $this->assertArrayHasKey('cache', $diag);
            $this->assertArrayHasKey('discovery', $diag);
            $this->assertArrayHasKey('performance', $diag);
        } else {
            $this->markTestSkipped('Diagnostics not available');
        }
    }
}
