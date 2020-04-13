<?php

use Daycry\Twig\Twig;

class TwigTest extends \CodeIgniter\Test\CIUnitTestCase
{
	protected $twig;
	protected $supportPath = null;

	public function setUp(): void
	{
		parent::setUp();

		$this->supportPath = realpath( __DIR__ . '/../_support/') . DIRECTORY_SEPARATOR;

		$config = new \Daycry\Twig\Config\Twig();

		$config->paths = [ $this->supportPath . 'Views' ];

		$this->twig = new Twig( $config );
	}

	public function testLoadTwig()
	{
		$result = $this->twig->render( 'test.html' );

		$this->assertEquals('<h1>daycry</h1>', $result );
	}

	public function testAddGlobal()
	{
		$session = array( 'name' => 'daycry' );
		
        $this->twig->addGlobal( 'session', $session );
		$result = $this->twig->render( 'test-global.html' );

		$this->assertEquals('<h1>daycry</h1>', $result );
	}
}