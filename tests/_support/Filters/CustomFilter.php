<?php

namespace Tests\Support\Filters;

class CustomFilter
{
    public static function run(string $string, array $arg = [])
    {
        return $string . '-modified';
    }
}
