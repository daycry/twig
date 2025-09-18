<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;
use Tests\Support\Extensions\TwigCustomExtension;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class TwigExtensionRegistrationTest extends CIUnitTestCase
{
    public function testRegisterExtensionBeforeInitialization()
    {
        $config = new TwigConfig();
        $config->paths = [];
        $twig = new Twig($config);
        $twig->registerExtension(TwigCustomExtension::class);
        $twig->withLoader(new ArrayLoader([
            'x.twig' => '{{ object|cast_to_array|json_encode|raw }}'
        ]));

        $o = new \stdClass();
        $o->a = 1; $o->b = 2;
        $out = $twig->render('x', ['object' => $o]);
        $this->assertSame('{"a":1,"b":2}', $out);
    }

    public function testRegisterExtensionAfterInitialization()
    {
        $config = new TwigConfig();
        $config->paths = [];
        $twig = new Twig($config);
        $twig->withLoader(new ArrayLoader([
            'base.twig' => 'Base',
            'y.twig' => '{{ object|cast_to_array|json_encode|raw }}'
        ]));

        // force environment creation with a template that does not need the extension
        $twig->render('base');

        $twig->registerExtension(TwigCustomExtension::class);
        $o = new \stdClass();
        $o->x = 'v';
        $out = $twig->render('y', ['object' => $o]);
        $this->assertSame('{"x":"v"}', $out);
    }
}
