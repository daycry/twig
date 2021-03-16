<?php

namespace Daycry\Twig;

use ReflectionObject;

require __DIR__ . '/../twig_functions.php';

class TwigTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        helper('url');
        helper('form');
    }

    public function testRedner()
    {
        $config = config( 'Daycry\Twig\Config\Twig' );
        $config->paths = [ __DIR__ . '/../templates/' ];

        $obj = new \Daycry\Twig\Twig( $config );

        $data = [
            'name' => 'CodeIgniter',
        ];
        $output = $obj->render('welcome', $data);
        $this->assertEquals('Hello CodeIgniter!' . "\n", $output);
    }

    public function testDisplay()
    {
        $config = config( 'Daycry\Twig\Config\Twig' );
        $config->paths = [ __DIR__ . '/../templates/' ];

        $obj = new \Daycry\Twig\Twig( $config );

        $this->expectOutputString('Hello CodeIgniter!' . "\n");

        $data = [
            'name' => 'CodeIgniter',
        ];
        $obj->display('welcome', $data);
    }

    public function testAddGlobal()
    {
        $config = config( 'Daycry\Twig\Config\Twig' );
        $config->paths = [ __DIR__ . '/../templates/' ];

        $obj = new \Daycry\Twig\Twig( $config );

        $obj->addGlobal('sitename', 'Twig Test Site');

        $output = $obj->render('global');
        $this->assertEquals('<title>Twig Test Site</title>' . "\n", $output);
    }

    public function testAddFunctionsRunsOnlyOnce()
    {
        $config = config( 'Daycry\Twig\Config\Twig' );
        $config->paths = [ __DIR__ . '/../templates/' ];

        $obj = new \Daycry\Twig\Twig( $config );

        $data = [
            'name' => 'CodeIgniter',
        ];

        $ref_obj = new ReflectionObject($obj);
        $ref_property = $ref_obj->getProperty('functions_added');
        $ref_property->setAccessible(true);
        $functions_added = $ref_property->getValue($obj);
        $this->assertEquals(false, $functions_added);

        $output = $obj->render('welcome', $data);

        $ref_obj = new ReflectionObject($obj);
        $ref_property = $ref_obj->getProperty('functions_added');
        $ref_property->setAccessible(true);
        $functions_added = $ref_property->getValue($obj);
        $this->assertEquals(true, $functions_added);

        // Calls render() twice
        $output = $obj->render('welcome', $data);

        $ref_obj = new ReflectionObject($obj);
        $ref_property = $ref_obj->getProperty('functions_added');
        $ref_property->setAccessible(true);
        $functions_added = $ref_property->getValue($obj);
        $this->assertEquals(true, $functions_added);
    }

    public function testFunctionAsIs()
    {
        $config = config( 'Daycry\Twig\Config\Twig' );
        $config->paths = [ __DIR__ . '/../templates/' ];
        $config->functions_asis = [ 'md5' ];
        $config->cache = false;

        $obj = new \Daycry\Twig\Twig( $config );

        $output = $obj->render('functions_asis');

        $this->assertEquals('900150983cd24fb0d6963f7d28e17f72' . "\n", $output);
    }

    public function testFunctionSafe()
    {
        $config = config( 'Daycry\Twig\Config\Twig' );
        $config->paths = [ __DIR__ . '/../templates/' ];
        $config->functions_safe = [ 'test_safe' ];
        $config->cache = false;

        $obj = new \Daycry\Twig\Twig( $config );

        $output = $obj->render('functions_safe');
        $this->assertEquals('<s>test</s>' . "\n", $output);
    }
}
