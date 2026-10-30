<?php

declare(strict_types=1);

namespace Tests\Standalone;

use Daycry\Twig\Configuration;
use Daycry\Twig\Contracts\CacheStoreInterface;
use Daycry\Twig\Persistence\StateRepository;
use Daycry\Twig\Twig;
use PHPUnit\Framework\TestCase;
use stdClass;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class CoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = dirname(__DIR__, 2) . '/build/standalone-' . uniqid();
        mkdir($this->directory . '/templates/sub', 0775, true);
    }

    private function renderer(?Configuration $config = null): Twig
    {
        $config ??= new Configuration();
        $config->paths     = [$this->directory . '/templates'];
        $config->cachePath = $this->directory . '/cache';

        return new Twig($config);
    }

    public function testStandaloneRenderEscapesAndUsesSavedContext(): void
    {
        $twig = (new Twig())->withLoader(new ArrayLoader(['hello.twig' => 'Hello {{ name }}']));
        $this->assertSame('Hello &lt;b&gt;', $twig->render('hello', ['name' => '<b>']));
        $this->assertSame('Hello &lt;b&gt;', $twig->render('hello'));
        $this->assertFalse($twig->isCacheEnabled());
    }

    public function testSaveDataCanBeDisabledAndExceptionsReleaseTemporaryData(): void
    {
        $config                  = new Configuration();
        $config->saveData        = false;
        $config->strictVariables = true;
        $twig                    = (new Twig($config))->withLoader(new ArrayLoader(['value.twig' => '{{ value }}']));
        $this->assertSame('one', $twig->render('value', ['value' => 'one']));

        try {
            $twig->render('value');
            $this->fail('Strict variables should reject the missing value.');
        } catch (RuntimeError) {
            $this->assertSame([], $twig->getData());
        }
        $this->assertSame('two', $twig->render('value', ['value' => 'two']));
    }

    public function testWarmupWritesRealCacheAndSkipsItOnAnotherInstance(): void
    {
        file_put_contents($this->directory . '/templates/one.twig', 'one');
        $twig = $this->renderer();
        $this->assertSame(['compiled' => 1, 'skipped' => 0, 'errors' => 0], $twig->warmupAll());
        $environment = $twig->getTwig();
        $key         = $environment->getCache(false)->generateKey('one.twig', $environment->getTemplateClass('one.twig'));
        $this->assertFileExists($key);
        $this->assertSame(['compiled' => 0, 'skipped' => 1, 'errors' => 0], $this->renderer()->warmupAll());
        unlink($key);
        $this->assertSame(1, $this->renderer()->warmupAll()['compiled']);
    }

    public function testForceWarmupChangesTheCompiledContents(): void
    {
        $source = $this->directory . '/templates/force.twig';
        file_put_contents($source, 'before');
        $twig = $this->renderer();
        $twig->warmup(['force']);
        $environment = $twig->getTwig();
        $key         = $environment->getCache(false)->generateKey('force.twig', $environment->getTemplateClass('force.twig'));
        file_put_contents($source, 'after');
        $this->assertSame(1, $twig->warmup(['force'], true)['compiled']);
        $this->assertStringContainsString('after', (string) file_get_contents($key));
    }

    public function testRealFilesystemInvalidationRefreshesLoadedTemplates(): void
    {
        $source = $this->directory . '/templates/one.twig';
        file_put_contents($source, 'before');
        $twig = $this->renderer();
        $this->assertSame('before', $twig->render('one'));
        file_put_contents($source, 'after');
        $this->assertSame(1, $twig->invalidateTemplate('one', true));
        $this->assertSame('after', $twig->render('one'));
    }

    public function testNestedDiscoverySeesNewAndRemovedTemplatesAcrossInstances(): void
    {
        file_put_contents($this->directory . '/templates/sub/one.twig', 'one');
        $this->assertSame(['sub/one'], $this->renderer()->listTemplates());
        $mtime = filemtime($this->directory . '/templates');
        file_put_contents($this->directory . '/templates/sub/two.twig', 'two');
        touch($this->directory . '/templates', $mtime);
        clearstatcache();
        $this->assertSame(['sub/one', 'sub/two'], $this->renderer()->listTemplates());
        unlink($this->directory . '/templates/sub/one.twig');
        $this->assertSame(['sub/two'], $this->renderer()->listTemplates());
    }

    public function testSnapshotRespectsTheConfiguredExtension(): void
    {
        file_put_contents($this->directory . '/templates/one.twig', 'one');
        file_put_contents($this->directory . '/templates/two.html', 'two');
        $this->assertSame(['one'], $this->renderer()->listTemplates());
        $config            = new Configuration();
        $config->extension = '.html';
        $this->assertSame(['two'], $this->renderer($config)->listTemplates());
    }

    public function testDiscoveryCacheHitDoesNotRewriteSnapshots(): void
    {
        $store = new class () implements CacheStoreInterface {
            public array $values = [];
            public int $writes   = 0;

            public function get(string $key): ?string
            {
                return $this->values[$key] ?? null;
            }

            public function save(string $key, string $value, int $ttl): bool
            {
                $this->writes++;
                $this->values[$key] = $value;

                return true;
            }

            public function delete(string $key): bool
            {
                unset($this->values[$key]);

                return true;
            }

            public function medium(): string
            {
                return 'memory';
            }

            public function backend(): string
            {
                return self::class;
            }
        };
        file_put_contents($this->directory . '/templates/one.twig', 'one');
        $config        = new Configuration();
        $config->paths = [$this->directory . '/templates'];
        $twig          = new Twig($config, null, $store);
        $this->assertSame(['one'], $twig->listTemplates());
        $writes = $store->writes;
        $this->assertSame(['one'], $twig->listTemplates());
        $this->assertSame($writes, $store->writes);
        $this->assertSame(1, $twig->getDiagnostics()['discovery']['hits']);
    }

    public function testWarmupEmitsExactlyOneNotification(): void
    {
        file_put_contents($this->directory . '/templates/one.twig', 'one');
        $events           = [];
        $config           = new Configuration();
        $config->onWarmup = static function (array $summary) use (&$events): void { $events[] = $summary; };
        $this->renderer($config)->warmupAll();
        $this->assertCount(1, $events);
        $this->assertSame('all', $events[0]['mode']);
    }

    public function testBatchInvalidationRemovesOnlySelectedTemplates(): void
    {
        foreach (['one', 'two', 'three'] as $name) {
            file_put_contents($this->directory . '/templates/' . $name . '.twig', $name);
        }
        $twig = $this->renderer();
        $twig->warmupAll();
        $result = $twig->invalidateTemplates(['one', 'two', 'one']);
        $this->assertSame(2, $result['removed']);
        $this->assertSame(['three'], array_keys(array_filter(array_column($twig->listTemplates(true), 'compiled', 'name'))));
    }

    public function testMalformedMetadataFallsBackToDiscovery(): void
    {
        $state = new StateRepository($this->directory . '/cache');
        $state->write('disc.list', ['list' => [new stdClass()], 'fingerprint' => 'invalid']);
        file_put_contents($this->directory . '/templates/one.twig', 'one');
        $this->assertSame(['one'], $this->renderer()->listTemplates());
    }

    public function testDeploymentVersionControlsPersistedDiscovery(): void
    {
        file_put_contents($this->directory . '/templates/one.twig', 'one');
        $config                   = new Configuration();
        $config->discoveryVersion = 'release-1';
        $this->assertSame(['one'], $this->renderer($config)->listTemplates());
        file_put_contents($this->directory . '/templates/two.twig', 'two');
        $this->assertSame(['one'], $this->renderer($config)->listTemplates());
        $config->discoveryVersion = 'release-2';
        $this->assertSame(['one', 'two'], $this->renderer($config)->listTemplates());
    }

    public function testCustomExtensionSharesTheInvalidationIndex(): void
    {
        file_put_contents($this->directory . '/templates/custom.html', 'custom');
        $config            = new Configuration();
        $config->extension = '.html';
        $twig              = $this->renderer($config);
        $twig->warmupAll();
        $this->assertTrue($twig->listTemplates(true)[0]['compiled']);
        $this->assertSame(1, $twig->invalidateTemplate('custom'));
        $this->assertFalse($twig->listTemplates(true)[0]['compiled']);
    }

    public function testResetReappliesDynamicFunctionsAndFilters(): void
    {
        $twig = $this->renderer();
        $twig->withLoader(new ArrayLoader(['dynamic.twig' => '{{ marker()|caps }}']));
        $twig->registerFunction('marker', static fn () => 'value');
        $twig->registerFilter('caps', strtoupper(...), []);
        $this->assertSame('VALUE', $twig->render('dynamic'));
        $twig->resetTwig();
        $this->assertSame('VALUE', $twig->render('dynamic'));
    }

    public function testInitializeReplacesConfiguredFilesystemPaths(): void
    {
        mkdir($this->directory . '/other');
        file_put_contents($this->directory . '/templates/same.twig', 'old');
        file_put_contents($this->directory . '/other/same.twig', 'new');
        $first        = new Configuration();
        $first->paths = [$this->directory . '/templates'];
        $twig         = new Twig($first);
        $this->assertSame('old', $twig->render('same'));
        $second        = new Configuration();
        $second->paths = [$this->directory . '/other'];
        $twig->initialize($second);
        $this->assertSame('new', $twig->render('same'));
    }

    public function testClearCacheRefreshesLoadedTemplatesAndIncludes(): void
    {
        file_put_contents($this->directory . '/templates/main.twig', '{{ include("sub/child.twig") }}');
        file_put_contents($this->directory . '/templates/sub/child.twig', 'before');
        $twig = $this->renderer();
        $this->assertSame('before', $twig->render('main'));
        file_put_contents($this->directory . '/templates/sub/child.twig', 'after');
        $twig->clearCache(true);
        $this->assertSame('after', $twig->render('main'));
    }

    public function testEmbeddedTemplatesAndGlobalsSurviveResets(): void
    {
        $twig = (new Twig())->withLoader(new ArrayLoader([
            'layout.twig'   => '{% block content %}base{% endblock %}',
            'embedded.twig' => '{% embed "layout.twig" %}{% block content %}{{ globalValue }}{% endblock %}{% endembed %}',
        ]));
        $twig->addGlobal('globalValue', 'global');
        $this->assertSame('global', $twig->render('embedded'));
        $twig->resetTwig();
        $this->assertSame('global', $twig->render('embedded'));
        $environment = $twig->getTwig();
        $this->assertSame($environment->getTemplateClass('embedded.twig') . '___1', $environment->getTemplateClass('embedded.twig', 1));
    }

    public function testNewFilesystemRendererUsesTheClearedGeneration(): void
    {
        file_put_contents($this->directory . '/templates/generation.twig', 'before');
        $first = $this->renderer();
        $this->assertSame('before', $first->render('generation'));
        file_put_contents($this->directory . '/templates/generation.twig', 'after');
        $first->clearCache(true);
        $this->assertSame('after', $first->render('generation'));
        $this->assertSame('after', $this->renderer()->render('generation'));
    }

    public function testWarmupAfterResetWritesTheSharedCacheClass(): void
    {
        $source = $this->directory . '/templates/shared.twig';
        file_put_contents($source, 'before');
        $twig = $this->renderer();
        $twig->render('shared');
        file_put_contents($source, 'after');
        $twig->resetTwig();
        $this->assertSame(1, $twig->warmup(['shared'], true)['compiled']);
        $fresh = $this->renderer()->getTwig();
        $key   = $fresh->getCache(false)->generateKey('shared.twig', $fresh->getTemplateClass('shared.twig'));
        $this->assertStringContainsString('after', (string) file_get_contents($key));
    }

    public function testEnableCacheMovesItsMetadataToTheNewDirectory(): void
    {
        file_put_contents($this->directory . '/templates/one.twig', 'one');
        $twig    = $this->renderer();
        $newPath = $this->directory . '/other-cache';
        $twig->enableCache($newPath);
        $twig->warmup(['one']);
        $this->assertSame($newPath . DIRECTORY_SEPARATOR . 'compile-index.json', $twig->getCompileIndexPath());
        $this->assertFileExists($twig->getCompileIndexPath());
    }

    public function testDiagnosticResetUsesTheConfiguredCacheBackend(): void
    {
        file_put_contents($this->directory . '/templates/reset.twig', 'before');
        $twig = $this->renderer();
        $twig->warmupAll();
        $index = $twig->getCompileIndexPath();
        $this->assertFileExists($index);
        $this->assertTrue($twig->resetDiagnostics(true)['ok']);
        $this->assertFileDoesNotExist($index);
        $this->assertSame('before', $twig->render('reset'));
        file_put_contents($this->directory . '/templates/reset.twig', 'after');
        $this->assertTrue($twig->resetDiagnostics(false, true)['ok']);
        $this->assertSame('after', $this->renderer()->render('reset'));
    }

    public function testDiagnosticResetReportsMetadataDeletionFailureWhenIncludingCache(): void
    {
        $store = $this->createMock(CacheStoreInterface::class);
        $store->method('delete')->willReturn(false);
        $twig   = new Twig(new Configuration(), store: $store);
        $result = $twig->resetDiagnostics(false, true);
        $this->assertFalse($result['ok']);
        $this->assertSame(['compiled_cache'], $result['failed']);
    }
}
