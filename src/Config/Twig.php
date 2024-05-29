<?php

namespace Daycry\Twig\Config;

use CodeIgniter\Config\BaseConfig;

class Twig extends BaseConfig
{
    public string $extension = '.twig';

    /**
     * @var list<string> functions_safe
     */
    public array $functions_safe = ['form_open', 'form_close', 'form_hidden', 'json_decode', 'form_error', 'form_hidden', 'set_value', 'csrf_field'];

    /**
     * @var list<string> functions_asis
     */
    public array $functions_asis = ['current_url', 'base_url', 'site_url'];

    /**
     * @var list<string> paths
     */
    public array $paths = [];

    /**
     * @var array<string,array<mixed,string>|string> filters
     */
    public array $filters = [];

    /**
     * When false, the view method will clear the data between each
     * call. This keeps your data safe and ensures there is no accidental
     * leaking between calls, so you would need to explicitly pass the data
     * to each view. You might prefer to have the data stick around between
     * calls so that it is available to all views. If that is the case,
     * set $saveData to true.
     */
    public bool $saveData = true;
}
