<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Twig\Config;

use Daycry\Twig\Debug\Toolbar\Collectors\Twig;

class Registrar
{
    public static function Toolbar(): array
    {
        return [
            'collectors' => [
                Twig::class,
            ],
        ];
    }
}
