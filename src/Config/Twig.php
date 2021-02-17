<?php

namespace Daycry\Twig\Config;

use CodeIgniter\Config\BaseConfig;

class Twig extends BaseConfig
{
    public $functions_safe = ['form_hidden', 'json_decode'];

    public $functions_asis = ['current_url'];

    public $paths = [];

    /**
     * Set a file format for template
     * 
     * Example: .twig, .html, or etc.
     */
    public $ext = '.html';
}
