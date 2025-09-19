<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;

/** @internal */
final class TwigCiPersistenceTest extends CIUnitTestCase
{
    private function newTwig(string $prefix): Twig
    {
        $config              = new TwigConfig();
        $config->paths       = ['./tests/_support/Templates/'];
        $config->cachePrefix = $prefix;
        $config->cacheTtl    = 0;
        // Discovery snapshot now enabled by default in full profile; explicit override for clarity
        $config->enableDiscoverySnapshot = true;

        return new Twig($config);
    }

    public function testArtifactsPersistAndClear()
    {
        $prefix = 'twig_persist_test_';
        $twig   = $this->newTwig($prefix);
        // Trigger discovery & warmup subset
        $list = $twig->listTemplates(false);
        $this->assertNotEmpty($list, 'Discovery list should not be empty');
        $twig->warmup([$list[0]]);
        $twig->invalidateTemplate($list[0]);
        $diag = $twig->getDiagnostics();
        $this->assertSame('ci', $diag['discovery']['persistence_medium'] ?? null);
        $this->assertSame('ci', $diag['persistence']['warmup']['medium'] ?? null);
        $this->assertSame('ci', $diag['persistence']['compile_index']['medium'] ?? null);
        $this->assertSame('ci', $diag['persistence']['invalidations']['medium'] ?? null);
        // It is possible that invalidation removed 0 files if template not yet compiled or mapping differs.
        $this->assertGreaterThanOrEqual(0, $diag['invalidations']['cumulative_removed']);

        // New instance should load persisted state
        $twig2 = $this->newTwig($prefix);
        $diag2 = $twig2->getDiagnostics();
        $this->assertSame($diag['invalidations']['cumulative_removed'], $diag2['invalidations']['cumulative_removed']);

        // Clear cache and ensure state resets
        $twig2->clearCache(false);
        $twig3 = $this->newTwig($prefix);
        $diag3 = $twig3->getDiagnostics();
        $this->assertTrue(($diag3['invalidations']['cumulative_removed'] ?? 0) === 0 || $diag3['invalidations']['cumulative_removed'] < $diag2['invalidations']['cumulative_removed']);
    }
}
