<?php

use Daycry\Twig\Config\Services;

if (! function_exists('view')) {
    /**
     * Grabs the current RendererInterface-compatible class
     * and tells it to render the specified view. Simply provides
     * a convenience method that can be used in Controllers,
     * libraries, and routed closures.
     *
     * NOTE: Does not provide any escaping of the data, so that must
     * all be handled manually by the developer.
     *
     * @param array $options Options for saveData or third-party extensions.
     */
    function view(string $name, array $data = []): string
    {
		$renderer = Services::twig();

		return $renderer->render($name, $data);
    }
}