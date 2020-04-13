<?php namespace Daycry\Twig\Config;

use CodeIgniter\Config\BaseConfig;

class Twig extends BaseConfig
{
    public $functions_safe = [ 'form_hidden', 'json_decode' ];
    
    public $functions_asis = [ 'current_url' ];

    public $paths = [];
}
