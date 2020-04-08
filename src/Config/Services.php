<?php namespace Daycry\Twig\Config;

use CodeIgniter\Config\BaseService;
use Daycry\Twig\Twig;

class Services extends BaseService
{
    public static function twig( bool $getShared = true )
    {
		if ( $getShared )
		{
			return static::getSharedInstance( 'twig' );
		}

		$config = config( 'Twig' );

		return new Twig( $config );
	}
}