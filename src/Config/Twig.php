<?php namespace Daycry\Twig\Config;

use CodeIgniter\Config\BaseConfig;

class Twig extends BaseConfig
{
    public $functions_safe = [ 'form_hidden', 'lang', 'json_decode' ];
    
    public $functions_asis = [ 'uri_string' ];
}
