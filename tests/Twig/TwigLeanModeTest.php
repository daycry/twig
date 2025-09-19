<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;

/** @internal */
final class TwigLeanModeTest extends CIUnitTestCase
{
    private function newTwig(callable $configure): Twig
    {
        $c        = new TwigConfig();
        $c->paths = ['./tests/_support/Templates/'];
        $configure($c);

        return new Twig($c);
    }

    public function testLeanModeDisablesCapabilities()
    {
        $twig = $this->newTwig(static function (TwigConfig $c): void {
            $c->leanMode = true; // do not set any overrides
        });
        // trigger some activity
        $twig->listTemplates(false);
        $diag = $twig->getDiagnostics();
        // Lean mode should now contain only core minimal sections plus capabilities.
        $this->assertArrayHasKey('capabilities', $diag);
        $this->assertArrayHasKey('cache', $diag);
        $this->assertArrayHasKey('performance', $diag);
        $this->assertArrayNotHasKey('names', $diag, 'Names list should be absent in lean mode');
        $this->assertArrayNotHasKey('dynamic_functions', $diag, 'Dynamic metrics removed entirely in lean mode');
        $this->assertArrayNotHasKey('dynamic_filters', $diag);
        $this->assertArrayNotHasKey('warmup', $diag);
        $this->assertArrayNotHasKey('invalidations', $diag);
        $this->assertArrayNotHasKey('discovery', $diag, 'Discovery snapshot section absent when capability disabled');
    }

    public function testLeanModeOverrideWarmupSummary()
    {
        $twig = $this->newTwig(static function (TwigConfig $c): void {
            $c->leanMode            = true;
            $c->enableWarmupSummary = true; // override back on
        });
        // perform warmup to create summary
        $list = $twig->listTemplates(false);
        if (! empty($list)) {
            $twig->warmup([$list[0]]);
        }
        $diag = $twig->getDiagnostics();
        $this->assertNotNull($diag['warmup'], 'Warmup summary expected when override enabled');
    }

    public function testFullModeHasExtendedDiagnostics()
    {
        $twig = $this->newTwig(static function (TwigConfig $c): void {
            $c->leanMode = false;
            // discoveryPersistList removed; snapshot disabled in lean by base profile
        });
        $twig->listTemplates(false);
        $diag = $twig->getDiagnostics();
        $this->assertArrayHasKey('names', $diag, 'Full mode should include names section');
        $this->assertIsArray($diag['names']);
        $this->assertIsArray($diag['dynamic_functions']);
    }
}
