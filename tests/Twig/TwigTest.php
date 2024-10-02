<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Services;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Twig;
use stdClass;
use Tests\Support\Extensions\TwigCustomExtension;
use Tests\Support\Filters\CustomFilter;
use Twig\Environment;

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
        $this->config->filters        = ['customFilter' => [CustomFilter::class, 'run']];
        $this->config->extensions     = [TwigCustomExtension::class];

        $this->twig = new Twig($this->config);
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    public function testConstructDefault()
    {
        $this->twig = new Twig();

        $this->assertInstanceOf(Environment::class, $this->twig->getTwig());
        $this->assertCount(1, $this->twig->getPaths());
    }

    public function testConstructCustomConfig()
    {
        $this->assertInstanceOf(Environment::class, $this->twig->getTwig());
        $this->assertCount(2, $this->twig->getPaths());
    }

    public function testConstructAsAService()
    {
        $this->twig = Services::twig(null, false);

        $this->assertInstanceOf(Environment::class, $this->twig->getTwig());
        $this->assertCount(1, $this->twig->getPaths());
    }

    public function testConstructAsAServiceCustomConfig()
    {
        $this->twig = Services::twig($this->config, false);

        $this->assertInstanceOf(Environment::class, $this->twig->getTwig());
        $this->assertCount(2, $this->twig->getPaths());
    }

    public function testConstructAsAHelper()
    {
        $this->twig = twig_instance();

        $this->assertInstanceOf(Environment::class, $this->twig->getTwig());
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

        $this->assertFalse($this->getPrivateProperty($this->twig, 'functions_added'));

        $output = $this->twig->render('welcome', $data);

        $this->assertSame("Hello CodeIgniter!\n", $output);
        $this->assertTrue($this->getPrivateProperty($this->twig, 'functions_added'));

        // Calls render() twice
        $output = $this->twig->render('welcome', $data);

        $this->assertSame("Hello CodeIgniter!\n", $output);
        $this->assertTrue($this->getPrivateProperty($this->twig, 'functions_added'));
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
        $object = new stdClass();
        $object->key = 'value';
        $output = $this->twig->render('custom_extension', ['object' => $object ]);
        $this->assertStringContainsString('key:value', $output);
    }
}
