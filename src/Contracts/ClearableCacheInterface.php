<?php

namespace Daycry\Twig\Contracts;

use Twig\Cache\CacheInterface;
use Twig\Cache\RemovableCacheInterface;

interface ClearableCacheInterface extends CacheInterface, RemovableCacheInterface
{
    public function clear(): int;
}
