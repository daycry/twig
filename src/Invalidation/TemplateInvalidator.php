<?php

namespace Daycry\Twig\Invalidation;

use Twig\Loader\FilesystemLoader;
use Daycry\Twig\Cache\TemplateCacheManager;
use Daycry\Twig\Discovery\TemplateDiscovery;

/**
 * Handles invalidation (single, batch, namespace) of compiled Twig templates.
 * Delegates discovery to TemplateDiscovery and compiled state tracking to TemplateCacheManager.
 */
class TemplateInvalidator
{
    public function __construct(
        private TemplateCacheManager $cacheManager,
        private TemplateDiscovery $discovery,
        private string $extension
    ) {}

    /** Compute md5 hash (logical name + extension) like Twig's compiled filename base. */
    private function compiledCacheHash(string $logicalName): string
    {
        return md5($logicalName . $this->extension);
    }

    /** Invalidate a single logical template (without extension). Returns removed file count. */
    public function invalidateOne(string $logicalName, string $cacheDir, bool $reinitialize, callable $resetTwig, callable $log): int
    {
        if ($cacheDir === '' || !is_dir($cacheDir)) { return 0; }
        $hash = $this->compiledCacheHash($logicalName);
        $removed = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        /** @var \SplFileInfo $file */
        foreach ($it as $file) {
            if (!$file->isFile()) { continue; }
            $path = $file->getPathname();
            if (strpos($path, $hash) !== false) {
                if (@unlink($path)) { $removed++; }
            }
        }
        if ($removed > 0) {
            $this->cacheManager->forget($logicalName);
            $log('info','event=twig.template.invalidated template='.$logicalName.' removed='.$removed);
            if ($reinitialize) { $resetTwig(); }
        }
        return $removed;
    }

    /** Batch invalidate logical template names (deduplicated). Returns array summary. */
    public function invalidateMany(array $logicalNames, string $cacheDir, bool $reinitialize, callable $resetTwig, callable $log): array
    {
        $unique = [];
        foreach ($logicalNames as $candidate) {
            $name = trim($candidate); if ($name === '' || isset($unique[$name])) { continue; }
            $unique[$name] = true;
        }
        $names = array_keys($unique);
        $deduplicated = count($logicalNames) - count($names);
        $count = count($names);
        if ($count === 0) { return ['removed'=>0,'templates'=>[],'reinit'=>false]; }
        if ($count === 1) {
            $removed = $this->invalidateOne($names[0], $cacheDir, $reinitialize, $resetTwig, $log);
            if ($removed > 0) { $log('info','event=twig.templates.invalidated count=1 removed='.$removed.' dedup='.$deduplicated); }
            return ['removed'=>$removed,'templates'=>$removed?[$names[0]=>$removed]:[],'reinit'=>$removed>0 && $reinitialize];
        }
        if ($cacheDir === '' || !is_dir($cacheDir)) { return ['removed'=>0,'templates'=>[],'reinit'=>false]; }
        $hashMap = [];$removedPer=[];
        foreach ($names as $logical) { $h=$this->compiledCacheHash($logical); $hashMap[$h]=$logical; $removedPer[$logical]=0; }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS));
        /** @var \SplFileInfo $file */
        foreach ($it as $file) {
            if (!$file->isFile()) { continue; }
            $path = $file->getPathname();
            foreach ($hashMap as $hash=>$logical) {
                if (strpos($path,$hash)!==false) { if (@unlink($path)) { $removedPer[$logical]++; } break; }
            }
        }
        $affected=[];$totalRemoved=0;
        foreach ($removedPer as $logical=>$cnt) { if ($cnt>0) { $affected[$logical]=$cnt; $this->cacheManager->forget($logical); $totalRemoved+=$cnt; } }
        if ($totalRemoved>0) {
            if ($reinitialize) { $resetTwig(); }
            $log('info','event=twig.templates.invalidated count='.count($affected).' removed='.$totalRemoved.' dedup='.$deduplicated.' optimized=1');
        }
        return ['removed'=>$totalRemoved,'templates'=>$affected,'reinit'=>$totalRemoved>0 && $reinitialize];
    }

    /** Invalidate namespace (with leading @) or root (null). */
    public function invalidateNamespace(?string $namespace, string $cacheDir, bool $reinitialize, FilesystemLoader $loader, callable $resetTwig, callable $log): array
    {
        $names = $this->discovery->listAll($loader, $this->extension);
        $target=[];
        if ($namespace === null) {
            foreach ($names as $n) { if ($n!=='' && $n[0] !== '@') { $target[]=$n; } }
        } else {
            $prefix = rtrim($namespace,'/').'/';
            foreach ($names as $n) { if (str_starts_with($n,$prefix) || $n===$namespace) { $target[]=$n; } }
        }
        $result = $this->invalidateMany($target, $cacheDir, $reinitialize, $resetTwig, $log);
        if ($result['removed']>0) { $log('info','event=twig.namespace.invalidated namespace='.($namespace ?? 'MAIN').' removed='.$result['removed']); }
        return $result;
    }
}
