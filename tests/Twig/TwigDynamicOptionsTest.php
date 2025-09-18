<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class TwigDynamicOptionsTest extends CIUnitTestCase
{
    public function testRegisterFunctionArrayOptionsSafe()
    {
        $config        = new TwigConfig();
        $config->paths = [];
        $twig          = new Twig($config);
        $twig->withLoader(new ArrayLoader([
            'f.twig' => '{{ bold_fn("X") }}',
        ]));
        $twig->registerFunction('bold_fn', static fn ($v) => '<b>' . $v . '</b>', ['is_safe' => ['html']]);
        $this->assertSame('<b>X</b>', $twig->render('f'));
    }

    public function testRegisterFunctionArrayOptionsEscaped()
    {
        $config        = new TwigConfig();
        $config->paths = [];
        $twig          = new Twig($config);
        $twig->withLoader(new ArrayLoader([
            'f2.twig' => '{{ ital_fn("X") }}',
        ]));
        $twig->registerFunction('ital_fn', static fn ($v) => '<i>' . $v . '</i>', []); // not safe
        $this->assertSame('&lt;i&gt;X&lt;/i&gt;', $twig->render('f2'));
    }

    public function testRegisterFilterArrayOptionsNotSafe()
    {
        $config        = new TwigConfig();
        $config->paths = [];
        $twig          = new Twig($config);
        $twig->withLoader(new ArrayLoader([
            't.twig' => '{{ "X"|wrapi }}',
        ]));
        $twig->registerFilter('wrapi', static fn ($v) => '<i>' . $v . '</i>', []);
        $this->assertSame('&lt;i&gt;X&lt;/i&gt;', $twig->render('t'));
    }

    public function testRegisterFilterArrayOptionsSafe()
    {
        $config        = new TwigConfig();
        $config->paths = [];
        $twig          = new Twig($config);
        $twig->withLoader(new ArrayLoader([
            't2.twig' => '{{ "X"|wrapb }}',
        ]));
        $twig->registerFilter('wrapb', static fn ($v) => '<b>' . $v . '</b>', ['is_safe' => ['html']]);
        $this->assertSame('<b>X</b>', $twig->render('t2'));
    }

    public function testBackwardCompatibilityBoolFunctionParam()
    {
        $config        = new TwigConfig();
        $config->paths = [];
        $twig          = new Twig($config);
        $twig->withLoader(new ArrayLoader([
            'g.twig' => '{{ legacy_fn() }}',
        ]));
        $twig->registerFunction('legacy_fn', static fn () => '<u>Y</u>', true); // bool safe
        $this->assertSame('<u>Y</u>', $twig->render('g'));
    }

    public function testBackwardCompatibilityBoolFilterParam()
    {
        $config        = new TwigConfig();
        $config->paths = [];
        $twig          = new Twig($config);
        $twig->withLoader(new ArrayLoader([
            'h.twig' => '{{ "Y"|legacy_filter }}',
        ]));
        $twig->registerFilter('legacy_filter', static fn ($v) => '<span>' . $v . '</span>', true);
        $this->assertSame('<span>Y</span>', $twig->render('h'));
    }
}
