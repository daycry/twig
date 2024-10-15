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
     * @var array<array<string,string>|string> paths
     *
     * A second parameter can be added to indicate the namespace of the view
     *
     * Example: public array $paths = [[APPPATH.'Module1/Views', 'module1'], APPPATH.'Module2/Views'];
     *
     * For use templates inside Module1
     * $twig->render('@module1/view')
     *
     * OR
     *
     * For use templates inside Module2
     *
     * $twig->render('view')
     */
    public array $paths = [];

    /**
     * @var array<string,array<mixed,string>|string> filters
     */
    public array $filters = [];

    /**
     * @var list<string> extensions
     */
    public array $extensions = [];

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
