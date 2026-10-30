<?php

declare(strict_types=1);

namespace Tests\Twig;

use CodeIgniter\Cache\Handlers\FileHandler;
use CodeIgniter\Filters\Filters;
use Config\Services;
use Daycry\Twig\CodeIgniter4\Config\Twig as TwigConfig;
use Daycry\Twig\CodeIgniter4\Twig;
use Tests\Support\ArrayCache;
use Tests\Support\TestCase as CIUnitTestCase;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class TwigRegressionTest extends CIUnitTestCase
{
    private function renderer(): Twig
    {
        Services::injectMock('cache', new FileHandler(config('Cache')));
        $config            = new TwigConfig();
        $config->cachePath = WRITEPATH . 'cache/regression-' . uniqid();
        $config->paths     = [__DIR__ . '/../_support/Templates'];

        return new Twig($config);
    }

    public function testInvalidationRemovesTheFileGeneratedByTwig(): void
    {
        $twig = $this->renderer();
        $twig->withLoader(new ArrayLoader(['actual.twig' => 'actual']));
        $twig->render('actual');
        $environment = $twig->getTwig();
        $cache       = $environment->getCache(false);
        $file        = $cache->generateKey('actual.twig', $environment->getTemplateClass('actual.twig'));
        $this->assertFileExists($file);
        $this->assertSame(1, $twig->invalidateTemplate('actual'));
        $this->assertFileDoesNotExist($file);
    }

    public function testWarmupAllInitializesItsLoader(): void
    {
        $result = $this->renderer()->warmupAll();
        $this->assertGreaterThan(0, $result['compiled'] + $result['errors']);
    }

    public function testSavedDataIsUsedByTheNextRender(): void
    {
        $twig = $this->renderer();
        $twig->withLoader(new ArrayLoader(['saved.twig' => '{{ value|default("missing") }}']));
        $this->assertSame('saved', $twig->render('saved', ['value' => 'saved']));
        $this->assertSame('saved', $twig->render('saved'));
    }

    public function testReinitializationKeepsTheFrameworkCacheBackend(): void
    {
        Services::injectMock('cache', new ArrayCache());
        $twig = new Twig(new TwigConfig());
        $twig->initialize(new TwigConfig());
        $this->assertSame('service', $twig->getDiagnostics()['cache']['mode']);
        $twig->disableCache();
        $this->assertFalse($twig->isCacheEnabled());
        $twig->withLoader(new ArrayLoader(['disabled.twig' => 'disabled']));
        $this->assertSame('disabled', $twig->render('disabled'));
        $this->assertFalse($twig->getDiagnostics()['cache']['enabled']);
    }

    public function testInheritedConfigurationPreservesItsFunctions(): void
    {
        $config = new class () extends TwigConfig {
            public array $functions_asis = ['md5'];
        };
        $twig = (new Twig($config))->withLoader(new ArrayLoader(['hash.twig' => '{{ md5("abc") }}']));
        $this->assertSame(md5('abc'), $twig->render('hash'));
    }

    public function testProductionRenderDoesNotResolveDebugFilters(): void
    {
        $filters = $this->createMock(Filters::class);
        $filters->expects($this->never())->method('getRequiredFilters');
        Services::injectMock('filters', $filters);
        $twig = new class (new TwigConfig()) extends Twig {
            public function useProductionMode(): void
            {
                $this->debug = false;
            }
        };
        $twig->useProductionMode();
        $twig->withLoader(new ArrayLoader(['production.twig' => 'production']));
        $this->assertSame('production', $twig->render('production'));
    }
}
