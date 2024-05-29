<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Traits\Viewable;
use Daycry\Twig\Twig;
use Tests\Support\Filters\CustomFilter;

/**
 * @internal
 */
final class TwigTraitTest extends CIUnitTestCase
{
    use Viewable;

    protected TwigConfig $config;
    protected Twig $twig;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();

        $this->config                 = new TwigConfig();
        $this->config->paths          = ['./tests/_support/Templates/'];
        $this->config->functions_asis = ['md5'];
        $this->config->filters        = ['customFilter' => [CustomFilter::class, 'run']];
    }

    public function testRender()
    {
        $data = [
            'name' => 'CodeIgniter',
        ];

        $output = $this->view('welcome', $data, $this->config);
        $this->assertSame("Hello CodeIgniter!\n", $output);
    }
}
