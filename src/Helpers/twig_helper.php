<?php

use Daycry\Twig\Config\Services;
use Daycry\Twig\Twig;

if (! function_exists('twig_instance')) {
    /**
     * Load the shared Twig service instance.
     */
    function twig_instance(): Twig
    {
        return Services::twig();
    }
}

if (! function_exists('twig_render')) {
    /**
     * Render a Twig template through the shared service. Equivalent to
     * `Services::twig()->render($view, $params)` but reads better in
     * controller code.
     */
    function twig_render(string $view, array $params = []): string
    {
        return Services::twig()->render($view, $params);
    }
}

if (! function_exists('twig_display')) {
    /**
     * Render and echo a Twig template through the shared service.
     */
    function twig_display(string $view, array $params = []): void
    {
        Services::twig()->display($view, $params);
    }
}

if (! function_exists('twig_capture')) {
    /**
     * Run a callable and return what it would have written to stdout. Useful
     * when integrating snippets that `display()` directly inside controllers
     * that need the resulting string instead.
     */
    function twig_capture(callable $fn): string
    {
        ob_start();

        try {
            $fn();

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();

            throw $e;
        }
    }
}
