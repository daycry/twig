<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class TwigTest extends CIUnitTestCase
{
    protected \Daycry\Twig\Config\Twig $config;
    protected \Daycry\Twig\Twig $twig;
    
    protected function setUp(): void
    {
        helper(array('url', 'form', 'twig_helper'));

        parent::setUp();

        $this->config = new \Daycry\Twig\Config\Twig();
        $this->config->paths = [ './tests/_support/templates/' ];
        $this->config->functions_asis = [ 'md5' ];

        $this->twig = new \Daycry\Twig\Twig( $this->config );
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    public function testConstructDefault()
    {
        $this->twig = new \Daycry\Twig\Twig();

        $this->assertInstanceOf( \Twig\Environment::class, $this->twig->getTwig());
        $this->assertCount( 1, $this->twig->getPaths());
    }

    public function testConstructCustomConfig()
    {
        $this->assertInstanceOf( \Twig\Environment::class, $this->twig->getTwig());
        $this->assertCount( 2, $this->twig->getPaths());
    }

    public function testConstructAsAService()
    {
        $this->twig = \Config\Services::twig(null, false);

        $this->assertInstanceOf( \Twig\Environment::class, $this->twig->getTwig());
        $this->assertCount( 1, $this->twig->getPaths());
    }

    public function testConstructAsAServiceCustomConfig()
    {
        $this->twig = \Config\Services::twig( $this->config, false );

        $this->assertInstanceOf( \Twig\Environment::class, $this->twig->getTwig());
        $this->assertCount( 2, $this->twig->getPaths());
    }

    public function testConstructAsAHelper()
    {
        $this->twig = twig_instance();

        $this->assertInstanceOf( \Twig\Environment::class, $this->twig->getTwig());
        $this->assertCount( 1, $this->twig->getPaths());
    }

    public function testRender()
    {
        $data = [
            'name' => 'CodeIgniter',
        ];
        $output = $this->twig->render('welcome', $data);
        $this->assertEquals('Hello CodeIgniter!' . "\n", $output);
    }

    public function testDisplay()
    {
        $data = [
            'name' => 'CodeIgniter',
        ];

        $this->twig->display('welcome', $data);

        $this->expectOutputString('Hello CodeIgniter!' . "\n");
    }

    public function testAddGlobal()
    {
        $this->twig->addGlobal('sitename', 'Global');

        $output = $this->twig->render('global');
        $this->assertEquals('<title>Global</title>' . "\n", $output);
    }

    public function testAddFunctionsRunsOnlyOnce()
    {
        $data = [
            'name' => 'CodeIgniter',
        ];

        $this->assertFalse($this->getPrivateProperty($this->twig, 'functions_added'));

        $output = $this->twig->render('welcome', $data);

        $this->assertEquals('Hello CodeIgniter!' . "\n", $output);
        $this->assertTrue($this->getPrivateProperty($this->twig, 'functions_added'));

        // Calls render() twice
        $output = $this->twig->render('welcome', $data);

        $this->assertEquals('Hello CodeIgniter!' . "\n", $output);
        $this->assertTrue($this->getPrivateProperty($this->twig, 'functions_added'));
    }

    public function testFunctionAsIs()
    {
        $output = $this->twig->render('functions_asis');
        $this->assertEquals('900150983cd24fb0d6963f7d28e17f72' . "\n", $output);
    }

    public function testFunctionSafe()
    {
        $this->config->functions_safe = [ 'functionSafe' ];
        $this->config->cache = false;

        $this->twig->initialize( $this->config );

        $output = $this->twig->render('functions_safe');
        $this->assertEquals('<s>test</s>' . "\n", $output);
    }
}
