<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class TwigStrictVariablesTest extends CIUnitTestCase
{
    public function testUndefinedVariableNotStrict()
    {
        $config        = new TwigConfig();
        $config->paths = []; // we will inject loader
        $twig          = new Twig($config);
        $twig->withLoader(new ArrayLoader([
            'u.twig' => 'Value: {{ missingVar|default("fallback") }}',
        ]));

        $output = $twig->render('u');
        $this->assertSame('Value: fallback', $output);
    }

    public function testUndefinedVariableStrictThrows()
    {
        $config                  = new TwigConfig();
        $config->strictVariables = true;
        $twig                    = new Twig($config);
        $twig->withLoader(new ArrayLoader([
            'u.twig' => 'Value: {{ missingVar }}',
        ]));

        $this->expectException(RuntimeError::class);
        $twig->render('u');
    }
}
