<?php

namespace Daycry\Twig\CodeIgniter4\Config;

use CodeIgniter\Config\BaseConfig;
use Daycry\Twig\Configuration;
use Daycry\Twig\Configuration\TwigOptions;

class Twig extends BaseConfig
{
    use TwigOptions;

    /**
     * Toolbar tuning: when many templates/functions or high traffic, the debug toolbar
     * panel can add overhead (name collection, template listing, json encoding, etc.).
     * These flags allow trimming what the collector does at runtime.
     */
    public bool $toolbarMinimal = false; // If true only core + cache + perf sections; skips discovery/warmup/invalidations/dynamics/template list/capabilities/persistence.

    public bool $toolbarShowTemplates    = true; // Show templates table (can be heavy if many templates)
    public int $toolbarMaxTemplates      = 50; // Hard cap for template rows
    public bool $toolbarShowCapabilities = true; // Show capabilities panel
    public bool $toolbarShowPersistence  = true; // Show persistence panel

    public function __construct()
    {
        if ($this->cachePath === false) {
            $this->cachePath = WRITEPATH . 'cache/twig';
        }
        $this->functions_asis = array_values(array_unique(array_merge(['current_url', 'base_url', 'site_url'], $this->functions_asis)));
        $this->functions_safe = array_values(array_unique(array_merge(['form_open', 'form_close', 'form_hidden', 'json_decode', 'form_error', 'set_value', 'csrf_field'], $this->functions_safe)));
        parent::__construct();
    }

    public function toCoreConfig(): Configuration
    {
        $options = new Configuration();

        foreach (get_object_vars($options) as $name => $value) {
            $options->{$name} = $this->{$name};
        }
        $options->paths          = array_merge([APPPATH . 'Views'], $options->paths);
        $options->functions_asis = array_values(array_unique(array_merge(['base_url', 'site_url'], $options->functions_asis)));
        $options->functions_safe = array_values(array_unique(array_merge(['form_open', 'form_close', 'form_error', 'form_hidden', 'set_value'], $options->functions_safe)));
        $options->debug          = ENVIRONMENT !== 'production';

        return $options;
    }
}
