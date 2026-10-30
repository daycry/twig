<?php

declare(strict_types=1);

namespace Tests\Twig;

use Daycry\Twig\CodeIgniter4\Config\Twig as TwigConfig;
use Daycry\Twig\CodeIgniter4\Twig;
use Tests\Support\TestCase as CIUnitTestCase;
use Tests\Support\Traits\TwigTestSetup;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class TwigLoaderTest extends CIUnitTestCase
{
    use TwigTestSetup;

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
