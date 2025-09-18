<?php

namespace Daycry\Twig\Cache;

/**
 * TemplateCacheManager centralizes cache path handling, compile index persistence,
 * and cache enable/disable operations. It is framework-agnostic and expects the
 * caller (facade) to provide actual filesystem paths and configuration mutations.
 */
class TemplateCacheManager
{
    /**
     * @var array<string,bool>
     */
    private array $compiledTemplates = [];

    private bool $compileIndexLoaded = false;
    private string $extension;
    private $logger; // callable|string|null log_message compatibility

    public function __construct(string $extension, $logger = null)
    {
        $this->extension = $extension;
        $this->logger    = $logger; // optional callable(level,string)
    }

    /**
     * Inject compiledTemplates array if already known (for warm restarts).
     */
    public function seedCompiled(array $names): void
    {
        foreach ($names as $n) {
            if (is_string($n)) {
                $this->compiledTemplates[$n] = true;
            }
        }
        $this->compileIndexLoaded = true; // assume authoritative
    }

    public function getCompiledTemplates(): array
    {
        return $this->compiledTemplates;
    }

    public function markCompiled(string $logical): void
    {
        $this->compiledTemplates[$logical] = true;
    }

    public function forget(string $logical): void
    {
        unset($this->compiledTemplates[$logical]);
    }

    /**
     * Load compile index if present.
     */
    public function loadIndex(string $indexPath): void
    {
        if ($this->compileIndexLoaded) {
            return;
        }
        if (is_file($indexPath)) {
            $json = @file_get_contents($indexPath);
            if ($json !== false) {
                $data = json_decode($json, true);
                if (is_array($data)) {
                    foreach ($data as $k => $v) {
                        if (is_string($k) && ($v === true || $v === 1)) {
                            $this->compiledTemplates[$k] = true;
                        }
                    }
                }
            }
        }
        $this->compileIndexLoaded = true;
    }

    /**
     * Persist compile index (creates file if directory exists).
     */
    public function saveIndex(string $indexPath): void
    {
        if (! $this->compileIndexLoaded) {
            $this->compileIndexLoaded = true;
        }
        $dir = dirname($indexPath);
        if (! is_dir($dir)) {
            return;
        }
        @file_put_contents($indexPath, json_encode($this->compiledTemplates, JSON_PRETTY_PRINT));
    }

    /**
     * Convenience: test if logical name is marked compiled (from index or current session).
     */
    public function isCompiled(string $logical): bool
    {
        return isset($this->compiledTemplates[$logical]);
    }
}
