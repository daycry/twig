<?php

namespace Daycry\Twig\Discovery;

use Twig\Loader\FilesystemLoader;

/**
 * TemplateDiscovery encapsulates template enumeration & in-process caching.
 * Responsibilities:
 *  - Discover logical template names from a FilesystemLoader
 *  - Maintain an in-process cache keyed by a context hash (loader class + extension + paths map)
 *  - Provide explicit cache invalidation hooks
 */
class TemplateDiscovery
{
    /** @var list<string>|null */
    private ?array $cache = null;
    private ?string $contextHash = null;

    /** Invalidate current discovery cache explicitly. */
    public function invalidate(): void
    {
        $this->cache = null;
        $this->contextHash = null;
    }

    /**
     * Returns list of logical template names (without extension) for provided loader & extension.
     * @return list<string>
     */
    public function listAll(FilesystemLoader $loader, string $extension): array
    {
        $pathsMap = $this->extractPathsMap($loader);
        if ($pathsMap === []) { return []; }
        $hash = md5(get_class($loader).'|'.$extension.'|'.json_encode($pathsMap));
        if ($this->cache !== null && $this->contextHash === $hash) {
            return $this->cache; // cache hit
        }
        $extLen = strlen($extension);
        $out = [];
        foreach ($pathsMap as $ns => $paths) {
            foreach ($paths as $base) {
                if (!is_dir($base)) { continue; }
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
                /** @var \SplFileInfo $file */
                foreach ($it as $file) {
                    if (!$file->isFile()) { continue; }
                    $filePath = $file->getPathname();
                    if (substr($filePath, -$extLen) !== $extension) { continue; }
                    $rel = ltrim(str_replace(['\\','/'],'/', substr($filePath, strlen($base))), '/');
                    $logical = substr($rel, 0, -$extLen);
                    if ($ns !== FilesystemLoader::MAIN_NAMESPACE) {
                        $logical = '@'.$ns.'/'.$logical;
                    }
                    $out[] = $logical;
                }
            }
        }
        $this->cache = $out;
        $this->contextHash = $hash;
        return $out;
    }

    /** Helper to extract internal paths map from loader via reflection. */
    private function extractPathsMap(FilesystemLoader $loader): array
    {
        try {
            $ref = new \ReflectionObject($loader);
            if (!$ref->hasProperty('paths')) { return []; }
            $prop = $ref->getProperty('paths');
            $prop->setAccessible(true);
            $pathsMap = $prop->getValue($loader);
            return is_array($pathsMap) ? $pathsMap : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
