<?php

namespace Daycry\Twig\Runtime;

use Daycry\Twig\Contracts\RefreshableCacheInterface;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * Runtime rebuilds and shared compiled artifacts have distinct class identities.
 */
final class Environment extends \Twig\Environment
{
    private array $persistentClasses  = [];
    private bool $compilingPersistent = false;

    public function __construct(LoaderInterface $loader, array $options = [], private readonly string $revision = '')
    {
        parent::__construct($loader, $options);
    }

    private function decorateClass(string $class, string $revision, ?int $index): string
    {
        $cache = $this->getCache(false);
        if ($cache instanceof RefreshableCacheInterface) {
            $revision .= ':' . $cache->revision();
        }
        if ($revision !== '') {
            $class .= '_' . hash('sha256', $revision);
        }

        return $index === null ? $class : $class . '___' . $index;
    }

    public function getPersistentTemplateClass(string $name, ?int $index = null): string
    {
        $this->persistentClasses[$name] ??= parent::getTemplateClass($name);

        return $this->decorateClass($this->persistentClasses[$name], '', $index);
    }

    public function getTemplateClass(string $name, ?int $index = null): string
    {
        if ($this->compilingPersistent) {
            return $this->getPersistentTemplateClass($name, $index);
        }
        $class = parent::getTemplateClass($name);
        $this->persistentClasses[$name] ??= $class;

        return $this->decorateClass($class, $this->revision, $index);
    }

    public function compilePersistentSource(Source $source): string
    {
        $previous                  = $this->compilingPersistent;
        $this->compilingPersistent = true;

        try {
            return parent::compileSource($source);
        } finally {
            $this->compilingPersistent = $previous;
        }
    }
}
