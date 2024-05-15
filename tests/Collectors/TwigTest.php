<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Collectors;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Twig\Debug\Toolbar\Collectors\Twig;

/**
 * @internal
 */
final class TwigTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace;
    protected $refresh = true;
    private Twig $collector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collector = new Twig();
    }

    public function testCollect()
    {
        $this->assertInstanceOf(Twig::class, $this->collector);
    }
}
