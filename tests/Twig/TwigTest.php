<?php

declare(strict_types=1);

namespace Tests\Twig;

use Daycry\Twig\CodeIgniter4\Config\Services;
use Daycry\Twig\CodeIgniter4\Config\Twig as TwigConfig;
use Daycry\Twig\CodeIgniter4\Twig;
use ReflectionProperty;
use stdClass;
use Tests\Support\Extensions\TwigCustomExtension;
use Tests\Support\Filters\CustomFilter;
use Tests\Support\TestCase as CIUnitTestCase;

/**
 * @internal
 */
final class TwigTest extends CIUnitTestCase
{
    protected TwigConfig $config;
    protected Twig $twig;

    protected function setUp(): void
    {
        helper(['url', 'form', 'twig_helper']);

        parent::setUp();

        $this->config                 = new TwigConfig();
        $this->config->paths          = ['./tests/_support/Templates/'];
        $this->config->functions_asis = ['md5'];
        $this->config->filters        = ['customFilter' => CustomFilter::run(...)];
        $this->config->extensions     = [TwigCustomExtension::class];

        $this->twig = new Twig($this->config);
    }

    public function testConstructDefault()
    {
        $this->twig = new Twig();

        $this->assertCount(1, $this->twig->getPaths());
    }

    public function testConstructCustomConfig()
    {
        $this->assertCount(2, $this->twig->getPaths());
    }

    public function testConstructAsAService()
    {
        $this->twig = Services::twig(null, false);

        $this->assertCount(1, $this->twig->getPaths());
    }

    public function testConstructAsAServiceCustomConfig()
    {
        $this->twig = Services::twig($this->config, false);

        $this->assertCount(2, $this->twig->getPaths());
    }

    public function testConstructAsAHelper()
    {
        $this->twig = twig_instance();

        $this->assertCount(1, $this->twig->getPaths());
    }

    public function testRender()
    {
        $data = [
            'name' => 'CodeIgniter',
        ];
        $output = $this->twig->render('welcome', $data);
        $this->assertSame("Hello CodeIgniter!\n", $output);
    }

    public function testDisplay()
    {
        $data = [
            'name' => 'CodeIgniter',
        ];

        $this->twig->display('welcome', $data);

        $this->expectOutputString("Hello CodeIgniter!\n");
    }

    public function testCreateTemplate()
    {
        $data = [
            'name' => 'CodeIgniter',
        ];

        echo $this->twig->createTemplate('Hello {{ name }}!', $data, false);

        $this->expectOutputString('Hello CodeIgniter!');
    }

    public function testCreateTemplateDisplay()
    {
        $data = [
            'name' => 'CodeIgniter',
        ];

        $this->twig->createTemplate('Hello {{ name }}!', $data, true);

        $this->expectOutputString('Hello CodeIgniter!');
    }

    public function testAddGlobal()
    {
        $this->twig->addGlobal('sitename', 'Global');

        $output = $this->twig->render('global');
        $this->assertSame("<title>Global</title>\n", $output);
    }

    public function testAddFunctionsRunsOnlyOnce()
    {
        $data = [
            'name' => 'CodeIgniter',
        ];

        $this->assertFalse((new ReflectionProperty(\Daycry\Twig\Twig::class, 'functions_added'))->getValue($this->twig));

        $output = $this->twig->render('welcome', $data);

        $this->assertSame("Hello CodeIgniter!\n", $output);
        $this->assertTrue((new ReflectionProperty(\Daycry\Twig\Twig::class, 'functions_added'))->getValue($this->twig));

        // Calls render() twice
        $output = $this->twig->render('welcome', $data);

        $this->assertSame("Hello CodeIgniter!\n", $output);
        $this->assertTrue((new ReflectionProperty(\Daycry\Twig\Twig::class, 'functions_added'))->getValue($this->twig));
    }

    public function testFunctionAsIs()
    {
        $output = $this->twig->render('functions_asis');
        $this->assertSame("900150983cd24fb0d6963f7d28e17f72\n", $output);
    }

    public function testFunctionSafe()
    {
        $this->config->functions_safe = ['functionSafe'];

        $this->twig->initialize($this->config);

        $output = $this->twig->render('functions_safe');
        $this->assertSame("<s>test</s>\n", $output);
    }

    public function testCustomFilters()
    {
        $output = $this->twig->render('custom_filter');
        $this->assertSame('hello-modified', $output);
    }

    public function testCustomExtensions()
    {
        $object      = new stdClass();
        $object->key = 'value';
        $output      = $this->twig->render('custom_extension', ['object' => $object]);
        $this->assertStringContainsString('key:value', $output);
    }

    public function testDuplicatePathsAreRemoved()
    {
        $config        = new TwigConfig();
        $config->paths = ['./tests/_support/Templates/', './tests/_support/Templates/'];
        $twig          = new Twig($config);
        $paths         = $twig->getPaths();
        // default APPPATH.'Views' + one deduplicated custom path = 2
        $this->assertCount(2, $paths);
        $this->assertSame(['./tests/_support/Templates/'], array_values(array_filter($paths, static fn ($p) => $p === './tests/_support/Templates/')));
    }
}
