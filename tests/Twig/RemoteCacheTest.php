<?php

declare(strict_types=1);

namespace Tests\Twig;

use CodeIgniter\Cache\Handlers\FileHandler;
use Config\Services;
use Daycry\Twig\CodeIgniter4\Cache\CacheStore;
use Daycry\Twig\CodeIgniter4\Cache\CICacheAdapter;
use Daycry\Twig\CodeIgniter4\Config\Twig;
use Tests\Support\ArrayCache;
use Tests\Support\TestCase;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class RemoteCacheTest extends TestCase
{
    public function testGenerationClearMakesOldEntriesUnreachableAndBounded(): void
    {
        $store  = new ArrayCache();
        $cache  = new CICacheAdapter($store, 'remote_', 0, 'test-signing-secret');
        $before = $cache->generateKey('one.twig', 'TemplateOne');
        $cache->write($before, '<?php // compiled');
        $this->assertSame(86400, $store->ttls[$before]);
        $this->assertGreaterThan(0, $cache->getTimestamp($before));
        $cache->clear();
        $after = $cache->generateKey('one.twig', 'TemplateOne');
        $this->assertNotSame($before, $after);
        $this->assertSame(0, $cache->getTimestamp($after));
        $other = new CICacheAdapter($store, 'remote_', 0, 'test-signing-secret');
        $this->assertSame($after, $other->generateKey('one.twig', 'TemplateOne'));
        $cache->write($after, '<?php // compiled');
        $cache->remove('one.twig', 'TemplateOne');
        $this->assertNull($store->get($after));
    }

    public function testSignedContentLoadsAndTamperedContentIsEvicted(): void
    {
        $store = new ArrayCache();
        $cache = new CICacheAdapter($store, 'signed_', 0, 'test-signing-secret');
        $class = 'SignedTemplate_' . uniqid();
        $key   = $cache->generateKey('signed.twig', $class);
        $cache->write($key, '<?php class ' . $class . ' {}');
        $cache->load($key);
        $this->assertTrue(class_exists($class, false));
        $payload      = json_decode($store->get($key), true);
        $payload['c'] = '<?php throw new \\RuntimeException("Must not execute");';
        $store->save($key, json_encode($payload), 60);
        $cache->load($key);
        $this->assertNull($store->get($key));
    }

    public function testGenerationLookupIsSharedAcrossTemplateKeys(): void
    {
        $store = new ArrayCache();
        $cache = new CICacheAdapter($store, 'lookup_', 0, 'test-secret');

        for ($i = 0; $i < 50; $i++) {
            $cache->generateKey('template' . $i, 'Class' . $i);
        }
        $this->assertSame(1, $store->getCalls);
    }

    public function testPersistentRendererObservesAnExternalGenerationClear(): void
    {
        $store = new ArrayCache();
        Services::injectMock('cache', $store);
        $directory = WRITEPATH . 'worker-' . uniqid();
        mkdir($directory, 0775, true);
        file_put_contents($directory . '/worker.twig', 'before');
        $config        = new Twig();
        $config->paths = [$directory];
        $twig          = new \Daycry\Twig\CodeIgniter4\Twig($config);
        $this->assertSame('before', $twig->render('worker'));
        $other = new CICacheAdapter($store, $twig->getDiagnostics()['cache']['prefix']);
        file_put_contents($directory . '/worker.twig', 'after');
        touch($directory . '/worker.twig', time() - 2);
        clearstatcache(true, $directory . '/worker.twig');
        $other->clear();
        $warmer = new \Daycry\Twig\CodeIgniter4\Twig($config);
        $warmer->warmup(['worker'], true);
        $writes = $store->compiledWrites;
        $this->assertSame('after', $twig->render('worker'));
        $this->assertSame($writes, $store->compiledWrites, 'The worker should reuse the shared warmed template.');
    }

    public function testNewRendererInTheSameWorkerUsesTheNewGeneration(): void
    {
        $store = new ArrayCache();
        Services::injectMock('cache', $store);
        $directory = WRITEPATH . 'new-worker-' . uniqid();
        mkdir($directory, 0775, true);
        file_put_contents($directory . '/worker.twig', 'before');
        $config        = new Twig();
        $config->paths = [$directory];
        $first         = new \Daycry\Twig\CodeIgniter4\Twig($config);
        $this->assertSame('before', $first->render('worker'));
        $cache = new CICacheAdapter($store, $first->getDiagnostics()['cache']['prefix']);
        file_put_contents($directory . '/worker.twig', 'after');
        $cache->clear();
        $second = new \Daycry\Twig\CodeIgniter4\Twig($config);
        $this->assertSame('after', $second->render('worker'));
    }

    public function testInvalidationRefreshesTheActiveRemoteGeneration(): void
    {
        $store = new ArrayCache();
        Services::injectMock('cache', $store);
        $directory = WRITEPATH . 'invalidation-generation-' . uniqid();
        mkdir($directory, 0775, true);
        file_put_contents($directory . '/one.twig', 'one');
        $config        = new Twig();
        $config->paths = [$directory];
        $first         = new \Daycry\Twig\CodeIgniter4\Twig($config);
        $first->warmup(['one']);
        $otherCache = new CICacheAdapter($store, $first->getDiagnostics()['cache']['prefix']);
        $otherCache->clear();
        $other = new \Daycry\Twig\CodeIgniter4\Twig($config);
        $other->warmup(['one']);
        $environment = $other->getTwig();
        $key         = $environment->getCache(false)->generateKey('one.twig', $environment->getTemplateClass('one.twig'));
        $this->assertNotNull($store->get($key));
        $first->invalidateTemplate('one');
        $this->assertNull($store->get($key));
    }

    public function testDiagnosticResetWorksWithRemoteMetadata(): void
    {
        $store = new ArrayCache();
        Services::injectMock('cache', $store);
        $twig = new \Daycry\Twig\CodeIgniter4\Twig();
        $twig->withLoader(new ArrayLoader(['reset.twig' => 'reset']));
        $twig->warmup(['reset']);
        $this->assertTrue($twig->resetDiagnostics(true)['ok']);
        $this->assertTrue($twig->resetDiagnostics(false, true)['ok']);
        $this->assertSame('reset', $twig->render('reset'));
    }

    public function testMetadataDeletionIsIdempotent(): void
    {
        $bridge = new CacheStore(new FileHandler(config('Cache')));
        $this->assertTrue($bridge->delete('missing_' . uniqid()));
    }
}
