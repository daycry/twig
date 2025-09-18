<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class TwigLoaderTest extends CIUnitTestCase
{
    public function testWithLoaderReplacesEnvironmentLoader()
    {
        $config = new TwigConfig();
        $twig   = new Twig($config);

        $loader = new ArrayLoader([
            'inline.twig' => 'Hello {{ name }}',
        ]);

        $twig->withLoader($loader);

        $output = $twig->render('inline', ['name' => 'Loader']);
        $this->assertSame('Hello Loader', $output);
    }
}
