<?php

declare(strict_types=1);
use Daycry\Twig\CodeIgniter4\Twig;
use Twig\Loader\ArrayLoader;

$directory = __DIR__ . '/writable/';

foreach (['cache', 'logs', 'session'] as $name) {
    if (! is_dir($directory . $name)) {
        mkdir($directory . $name, 0775, true);
    }
}
define('WRITEPATH', $directory);
define('HOMEPATH', __DIR__ . '/');
define('TESTPATH', __DIR__ . '/');
require __DIR__ . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';

$twig = service('twig');
if (! $twig instanceof Twig) {
    throw new RuntimeException('The framework did not discover the adapter service.');
}
helper('twig_helper');
$twig->withLoader(new ArrayLoader(['hello.twig' => '{{ base_url("hello") }}']));
if (! str_contains(twig_render('hello'), 'hello')) {
    throw new RuntimeException('The adapter helper or URL function was not registered.');
}
$commands = service('commands')->getCommands();

foreach (['twig:publish', 'twig:lint', 'twig:warmup', 'twig:doctor'] as $name) {
    if (! isset($commands[$name])) {
        throw new RuntimeException('The framework did not discover command ' . $name);
    }
}
$exit = service('commands')->run('twig:reset-metrics', ['--include-cache', '--json']);
if ($exit !== EXIT_SUCCESS) {
    throw new RuntimeException('The backend-aware diagnostic reset command failed.');
}
echo "CodeIgniter consumer OK; service, helper, Spark discovery and reset command verified.\n";
