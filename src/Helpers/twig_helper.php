<?php

if( !function_exists( 'twig_instance' ) )
{
	/**
	 * load twig
	 *
	 * @return class
	 */
	function twig_instance()
	{
		return \CodeIgniter\Config\Services::twig();
	}
}