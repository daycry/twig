<?php

namespace Daycry\Twig\Cache;

use Daycry\Twig\Contracts\ClearableCacheInterface;
use Daycry\Twig\Contracts\RefreshableCacheInterface;
use Daycry\Twig\Support\AtomicFileWriter;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A generation marker also invalidates classes already loaded in other renderers.
 */
final class FilesystemCache extends \Twig\Cache\FilesystemCache implements ClearableCacheInterface, RefreshableCacheInterface
{
    private ?string $generation = null;
    private readonly string $generationFile;

    public function __construct(private readonly string $directory)
    {
        parent::__construct($directory);
        $this->generationFile = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '.twig-generation';
    }

    private function readGeneration(): string
    {
        $value = @file_get_contents($this->generationFile);

        return is_string($value) && preg_match('/^[a-f0-9]{32}$/D', $value) === 1 ? $value : '0';
    }

    public function revision(): string
    {
        $this->generation ??= $this->readGeneration();

        return $this->generation;
    }

    public function refresh(): bool
    {
        $generation       = $this->readGeneration();
        $changed          = $this->generation !== null && $generation !== $this->generation;
        $this->generation = $generation;

        return $changed;
    }

    public function clear(): int
    {
        $removed = 0;
        if (is_dir($this->directory)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'php' && @unlink($file->getPathname())) {
                    $removed++;
                }
            }
        }
        $generation = bin2hex(random_bytes(16));
        AtomicFileWriter::write($this->generationFile, $generation);
        $this->generation = $generation;

        return $removed;
    }
}
