<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Cache\Handlers\FileHandler;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

abstract class TestCase extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetServices();
        Services::injectMock('cache', new FileHandler(config('Cache')));
    }
}
