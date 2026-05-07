<?php

declare(strict_types=1);

namespace Tests\Twig;

use Daycry\Twig\Config\CapabilitiesProfile;
use Daycry\Twig\Config\Twig as TwigConfig;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CapabilitiesProfileTest extends TestCase
{
    public function testFullProfileEnablesEverything(): void
    {
        $cfg           = new TwigConfig();
        $cfg->leanMode = false;
        $p             = CapabilitiesProfile::fromConfig($cfg);
        $this->assertTrue($p->discoverySnapshot);
        $this->assertTrue($p->warmupSummary);
        $this->assertTrue($p->invalidationHistory);
        $this->assertTrue($p->dynamicMetrics);
        $this->assertTrue($p->extendedDiagnostics);
    }

    public function testLeanProfileDisablesEverything(): void
    {
        $cfg           = new TwigConfig();
        $cfg->leanMode = true;
        $p             = CapabilitiesProfile::fromConfig($cfg);
        $this->assertFalse($p->discoverySnapshot);
        $this->assertFalse($p->warmupSummary);
        $this->assertFalse($p->invalidationHistory);
        $this->assertFalse($p->dynamicMetrics);
        $this->assertFalse($p->extendedDiagnostics);
    }

    public function testNullableOverridesWinOverProfile(): void
    {
        $cfg                            = new TwigConfig();
        $cfg->leanMode                  = true;
        $cfg->enableWarmupSummary       = true;
        $cfg->enableExtendedDiagnostics = false;
        $p                              = CapabilitiesProfile::fromConfig($cfg);
        $this->assertFalse($p->discoverySnapshot, 'lean default');
        $this->assertTrue($p->warmupSummary, 'override true');
        $this->assertFalse($p->extendedDiagnostics, 'override false (still false in lean)');
    }

    public function testToArrayKeysAreStable(): void
    {
        $p = new CapabilitiesProfile(true, false, true, false, true);
        $this->assertSame(
            ['discoverySnapshot', 'warmupSummary', 'invalidationHistory', 'dynamicMetrics', 'extendedDiagnostics'],
            array_keys($p->toArray()),
        );
    }
}
