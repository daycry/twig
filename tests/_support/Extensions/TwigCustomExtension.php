<?php

declare(strict_types=1);

namespace Tests\Support\Extensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TwigCustomExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('cast_to_array', [$this, 'castToArray']),
        ];
    }

    public function castToArray($object, array $options = []): array
    {
        $response = [];
        foreach ($object as $key => $value) {
            $response[$key] = $value;
        }

        return $response;
    }
}