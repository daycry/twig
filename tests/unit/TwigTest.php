<?php

use Daycry\Twig\Twig;

class TwigTest extends \CodeIgniter\Test\CIUnitTestCase
{
	protected $twig;

	public function setUp(): void
	{
		parent::setUp();

		$config = new \Daycry\Twig\Config\Twig();

		$config->paths = [ SUPPORTPATH . 'Views' ];

		$this->twig = new Twig( $config );
	}

	public function testLoadTwig()
	{
		$result = $this->twig->render( 'base.html' );

		$this->assertEquals('<h1>daycry</h1>', $result );
	}
}