<?php

namespace Daycry\Twig;

use Daycry\Twig\Config\Twig as TwigConfig;
use CodeIgniter\Config\BaseConfig;

use CodeIgniter\Exceptions\PageNotFoundException;
use Twig_Environment;
use Twig_Error_Loader;
use Twig_Extension_Debug;
use Twig_Loader_Filesystem;

/**
 * Class General
 *
 * @package App\Libraries
 */
class Twig
{
    /**
    * @var array Paths to Twig templates
    */
    private $paths = [];
    
    /**
    * @var array Functions to add to Twig
    */
    private $functions_asis = [ 'base_url', 'site_url' ];

    /**
     * @var array Functions with `is_safe` option
     * @see http://twig.sensiolabs.org/doc/advanced.html#automatic-escaping
     */
    private $functions_safe = [
        'form_open', 'form_close', 'form_error', 'form_hidden', 'set_value'
    ];
    
    /**
    * @var array Twig Environment Options
    * @see http://twig.sensiolabs.org/doc/api.html#environment-options
    */
    private $config = [];

    
    /**
    * @var bool Whether functions are added or not
    */
    private $functions_added = FALSE;

    /**
     * @var Twig_Environment
     */
    private $twig;

    /**
     * @var Twig_Loader_Filesystem
     */
    private $loader;
    
    /**
     * @var string
     */
    private $ext = '.twig';
    
    
    public function __construct( BaseConfig $config = null )
    {
        if( empty( $config ) )
        {
            $config = config( 'Twig' );
        }

        if( isset( $config->functions_asis ) )
        {
            $this->functions_asis = array_unique( array_merge( $this->functions_asis, $config->functions_asis ) );
        }
        
        if( isset( $config->functions_safe ) )
        {
            $this->functions_safe = array_unique( array_merge( $this->functions_safe, $config->functions_safe ) );
        }

        $this->paths = ( isset( $config->paths ) ) ? $config->paths : APPPATH . 'Views';
        
        // default Twig config
        $this->config = [
            'cache'      => WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'twig',
            'debug'      => ENVIRONMENT !== 'production',
            'autoescape' => 'html'
        ];
    }
    
    protected function resetTwig()
    {
        $this->twig = null;
        $this->createTwig();
    }
    
    protected function createTwig()
    {
        // $this->twig is singleton
        if( $this->twig !== null )
        {
            return;
        }

        if( $this->loader === null )
        {
            $this->loader = new \Twig_Loader_Filesystem( $this->paths );
        }

        $twig = new \Twig_Environment( $this->loader, $this->config );

        if( $this->config[ 'debug' ] )
        {
            $twig->addExtension( new \Twig_Extension_Debug() );
        }

        $this->twig = $twig;
    }
    
    protected function setLoader($loader)
    {
        $this->loader = $loader;
    }
    
    /**
    * Registers a Global
    *
    * @param string $name  The global name
    * @param mixed  $value The global value
    */
    public function addGlobal( $name, $value )
    {
        $this->createTwig();
        $this->twig->addGlobal( $name, $value );
    }
   
    protected function addFunctions()
    {
        // Runs only once
        if( $this->functions_added )
        {
            return;
        }

        // as is functions
        foreach( $this->functions_asis as $function )
        {
            if ( function_exists( $function ) )
            {
                $this->twig->addFunction( new \Twig_SimpleFunction( $function, $function ) );
            }
        }

        // safe functions
        foreach( $this->functions_safe as $function )
        {
            if (function_exists($function))
            {
                $this->twig->addFunction( new \Twig_SimpleFunction( $function, $function, [ 'is_safe' => [ 'html' ] ] ) );
            }
        }

        // customized functions
        if( function_exists( 'anchor' ) )
        {
            $this->twig->addFunction( new \Twig_SimpleFunction( 'anchor', [ $this, 'safe_anchor' ], [ 'is_safe' => [ 'html' ] ] ) );
        }

        $this->functions_added = TRUE;
    }
    
    /**
    * @param string $uri
    * @param string $title
    * @param array  $attributes [changed] only array is acceptable
    * @return string
    */
    public function safe_anchor( $uri = '', $title = '', $attributes = [] )
    {
        $uri = esc( $uri );
        $title = esc( $title );

        $new_attr = [];
        foreach( $attributes as $key => $val )
        {
                $new_attr[ esc( $key ) ] = esc( $val );
        }

        return anchor( $uri, $title, $new_attr );
    }

    /**
     * @return \Twig_Environment
     */
    public function getTwig()
    {
        $this->createTwig();
        return $this->twig;
    }
    
    /**
    * Renders Twig Template and Set Output
    *
    * @param string $view   Template filename without `.twig`
    * @param array  $params Array of parameters to pass to the template
    */
    public function display( string $view, array $params = [] )
    {
        echo $this->render( $view, $params );
    }
    
    /**
    * Renders Twig Template and Returns as String
    *
    * @param string $view   Template filename without `.twig`
    * @param array  $params Array of parameters to pass to the template
    * @return string
    */
    public function render( string $view, array $params = [] ): string
    {
        try
        {
            $this->createTwig();
            // We call addFunctions() here, because we must call addFunctions()
            // after loading CodeIgniter functions in a controller.
            $this->addFunctions();
            
            $view = $view . '.twig';
            return $this->twig->render( $view, $params );
        } 
        catch( Twig_Error_Loader $error_Loader ) 
        {
            throw new PageNotFoundException($error_Loader);
        }
    }
}