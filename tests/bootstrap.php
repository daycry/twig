<?php

declare(strict_types=1);

$writePath = dirname(__DIR__) . '/build/test-writable/';

foreach (['cache', 'logs', 'session'] as $directory) {
    if (! is_dir($writePath . $directory)) {
        mkdir($writePath . $directory, 0775, true);
    }
}
defined('WRITEPATH') || define('WRITEPATH', $writePath);
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
