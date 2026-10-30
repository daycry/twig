<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use CodeIgniter\CodeIgniter;
use Daycry\Twig\Configuration;
use Daycry\Twig\Twig;
use Twig\Loader\ArrayLoader;

if (class_exists(CodeIgniter::class) || function_exists('config') || defined('APPPATH')) {
    throw new RuntimeException('The standalone consumer must not load CodeIgniter.');
}
$config            = new Configuration();
$config->cachePath = __DIR__ . '/writable/cache';
$twig              = (new Twig($config))->withLoader(new ArrayLoader(['hello.twig' => 'Hello {{ name }}']));
if ($twig->render('hello', ['name' => '<Ada>']) !== 'Hello &lt;Ada&gt;' || $twig->render('hello') !== 'Hello &lt;Ada&gt;') {
    throw new RuntimeException('Standalone rendering or saved context failed.');
}
if ($twig->invalidateTemplate('hello') !== 1) {
    throw new RuntimeException('Standalone cache invalidation failed.');
}
echo "Standalone consumer OK; CodeIgniter absent.\n";
