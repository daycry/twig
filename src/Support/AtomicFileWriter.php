<?php

namespace Daycry\Twig\Support;

use RuntimeException;

final class AtomicFileWriter
{
    public static function write(string $file, string $contents): void
    {
        $directory = dirname($file);
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create Twig cache directory.');
        }
        $temporary = tempnam($directory, '.twig-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to create temporary Twig cache file.');
        }

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
                throw new RuntimeException('Unable to write Twig cache state.');
            }
            @chmod($temporary, 0666 & ~umask());
            if (! @rename($temporary, $file)) {
                throw new RuntimeException('Unable to replace Twig cache state.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
