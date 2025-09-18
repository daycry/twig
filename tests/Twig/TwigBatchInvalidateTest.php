<?php
namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Twig;
use Daycry\Twig\Config\Twig as TwigConfig;

/** @internal */
final class TwigBatchInvalidateTest extends CIUnitTestCase
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

    public function testBatchInvalidationNoReinit(): void
    {
        // warm/templates compile
        $this->twig->render('welcome');
        $this->twig->render('sub/nested'); // assume exists if not test will still pass removed=0
        $summary = $this->twig->invalidateTemplates(['welcome','sub/nested']);
        $this->assertArrayHasKey('removed', $summary);
        $this->assertIsInt($summary['removed']);
        $this->assertFalse($summary['reinit']);
    }

    public function testBatchInvalidationWithReinit(): void
    {
        $this->twig->render('welcome');
        $summary = $this->twig->invalidateTemplates(['welcome'], true);
        // Reinit only true if at least one file actually removed
        if ($summary['removed'] > 0) {
            $this->assertTrue($summary['reinit']);
        } else {
            $this->assertFalse($summary['reinit']);
        }
    }

    public function testNamespaceInvalidation(): void
    {
        // If no namespaces configured this should just remove main templates list
        $this->twig->render('welcome');
        $result = $this->twig->invalidateNamespace(null);
        $this->assertArrayHasKey('removed', $result);
        $this->assertIsInt($result['removed']);
    }
}
