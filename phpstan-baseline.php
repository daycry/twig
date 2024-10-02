<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined static method Config\\\\Services\\:\\:twig\\(\\).$#'
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];