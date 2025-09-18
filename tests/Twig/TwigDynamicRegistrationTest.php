<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class TwigDynamicRegistrationTest extends CIUnitTestCase
{
    public function testRegisterFunctionBeforeInitializationIsQueued()
    {
        $config        = new TwigConfig();
        $config->paths = []; // we'll inject our own loader
        $twig          = new Twig($config);
        $twig->registerFunction('hello_fn', static fn (string $name): string => 'Hello ' . $name);
        $twig->withLoader(new ArrayLoader([
            'greet.twig' => '{{ hello_fn("World") }}',
        ]));

        $output = $twig->render('greet');
        $this->assertSame('Hello World', $output);
    }

    public function testRegisterFunctionAfterInitializationAddsImmediately()
    {
        $config        = new TwigConfig();
        $config->paths = [];
        $twig          = new Twig($config);
        $twig->withLoader(new ArrayLoader([
            'greet.twig' => '{{ dynamic_fn("CI4") }}',
        ]));

        // Force initialization by first render of a dummy template
        $twig->registerFunction('dynamic_fn', static fn (string $name): string => 'Hi ' . $name);
        $out = $twig->render('greet');
        $this->assertSame('Hi CI4', $out);
    }

    public function testRegisterFilterBeforeInitializationIsQueued()
    {
        $config        = new TwigConfig();
        $config->paths = [];
        $twig          = new Twig($config);
        $twig->registerFilter('exclaim', static fn (string $v): string => $v . '!');
        $twig->withLoader(new ArrayLoader([
            'shout.twig' => '{{ "wow"|exclaim }}',
        ]));

        $out = $twig->render('shout');
        $this->assertSame('wow!', $out);
    }

    public function testRegisterUnsafeFunctionIsEscaped()
    {
        $config        = new TwigConfig();
        $config->paths = [];
        $twig          = new Twig($config);
        $twig->registerFunction('raw_fn', static fn (): string => '<b>x</b>'); // not marked safe
        $twig->withLoader(new ArrayLoader([
            't.twig' => '{{ raw_fn() }}',
        ]));

        $out = $twig->render('t');
        $this->assertSame('&lt;b&gt;x&lt;/b&gt;', $out);
    }

    public function testRegisterSafeFunctionNotEscaped()
    {
        $config        = new TwigConfig();
        $config->paths = [];
        $twig          = new Twig($config);
        $twig->registerFunction('safe_fn', static fn (): string => '<i>ok</i>', true);
        $twig->withLoader(new ArrayLoader([
            't.twig' => '{{ safe_fn() }}',
        ]));

        $out = $twig->render('t');
        $this->assertSame('<i>ok</i>', $out);
    }

    public function testRegisterUnsafeFilterIsEscaped()
    {
        $config        = new TwigConfig();
        $config->paths = [];
        $twig          = new Twig($config);
        // Mark filter unsafe by passing false
        $twig->registerFilter('wrapi', static fn (string $v): string => '<i>' . $v . '</i>', false);
        $twig->withLoader(new ArrayLoader([
            't.twig' => '{{ "txt"|wrapi }}',
        ]));

        $out = $twig->render('t');
        $this->assertSame('&lt;i&gt;txt&lt;/i&gt;', $out);
    }

    public function testRegisterSafeFilterNotEscaped()
    {
        $config        = new TwigConfig();
        $config->paths = [];
        $twig          = new Twig($config);
        $twig->registerFilter('wrapb', static fn (string $v): string => '<b>' . $v . '</b>', true);
        $twig->withLoader(new ArrayLoader([
            't.twig' => '{{ "txt"|wrapb }}',
        ]));

        $out = $twig->render('t');
        $this->assertSame('<b>txt</b>', $out);
    }
}
