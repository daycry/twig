<?php

namespace Daycry\Twig\Invalidation;

use Daycry\Twig\Contracts\CacheManagerInterface;
use Daycry\Twig\Contracts\DiscoveryInterface;
use Daycry\Twig\Contracts\InvalidatorInterface;
use Daycry\Twig\Runtime\Environment as ManagedEnvironment;
use Twig\Cache\RemovableCacheInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;

class TemplateInvalidator implements InvalidatorInterface
{
    public function __construct(
        private readonly CacheManagerInterface $cacheManager,
        private readonly DiscoveryInterface $discovery,
        private readonly string $extension,
    ) {
    }

    public function invalidateOne(string $logicalName, Environment $environment, bool $reinitialize, callable $resetTwig, callable $log): int
    {
        $result = $this->invalidateMany([$logicalName], $environment, $reinitialize, $resetTwig, $log);

        return $result['removed'];
    }

    public function invalidateMany(array $logicalNames, Environment $environment, bool $reinitialize, callable $resetTwig, callable $log): array
    {
        $removed  = 0;
        $affected = [];
        $cache    = $environment->getCache(false);

        foreach (array_unique($logicalNames) as $logical) {
            $this->cacheManager->forget($logical);
            if (! $cache instanceof RemovableCacheInterface) {
                continue;
            }
            $name = $logical . $this->extension;

            try {
                $current    = $environment->getTemplateClass($name);
                $persistent = $environment instanceof ManagedEnvironment ? $environment->getPersistentTemplateClass($name) : $current;
                $count      = 0;
                if ($persistent !== $current) {
                    $persistentKey = $cache->generateKey($name, $persistent);
                    if ($cache->getTimestamp($persistentKey) > 0) {
                        $count++;
                    }
                    $cache->remove($name, $persistent);
                }
                $key = $cache->generateKey($name, $current);
                if ($cache->getTimestamp($key) > 0) {
                    $count++;
                }
                $environment->removeCache($name);
                if ($count > 0) {
                    $affected[$logical] = $count;
                    $removed += $count;
                }
            } catch (LoaderError) {
                // A deleted source has no current loader key; its index entry is still forgotten.
            }
        }
        if ($reinitialize && $removed > 0) {
            $resetTwig();
        }
        if ($removed > 0) {
            $log('info', 'event=twig.templates.invalidated removed=' . $removed);
        }

        return ['removed' => $removed, 'templates' => $affected, 'reinit' => $reinitialize && $removed > 0];
    }

    public function invalidateNamespace(?string $namespace, Environment $environment, bool $reinitialize, FilesystemLoader $loader, callable $resetTwig, callable $log): array
    {
        $names = $this->discovery->listAll($loader, $this->extension);
        $names = array_values(array_filter($names, static fn (string $name): bool => $namespace === null
            ? ! str_starts_with($name, '@') : str_starts_with($name, $namespace . '/')));

        return $this->invalidateMany($names, $environment, $reinitialize, $resetTwig, $log);
    }
}
