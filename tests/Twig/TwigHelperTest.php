<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class TwigHelperTest extends CIUnitTestCase
{
    private $twig;

    protected function setUp(): void
    {
        helper(array('url'));

        parent::setUp();

        $this->config = new \Daycry\Twig\Config\Twig();
        $this->config->paths = [ './tests/_support/templates/' ];
        $this->config->functions_asis = [ 'md5' ];

        $this->twig = new \Daycry\Twig\Twig( $this->config );

        $loader = new \Twig\Loader\ArrayLoader(
            [
                'base_url' => '{{ base_url(\'"><s>abc</s><a name="test\') }}',
                'site_url' => '{{ site_url(\'"><s>abc</s><a name="test\') }}',
                'anchor' => '{{ anchor(uri, title, attributes) }}',
            ]
        );
        $setLoader = $this->getPrivateMethodInvoker($this->twig, 'setLoader');
        $setLoader($loader);

        $this->twig->resetTwig();

        $addFunctions = $this->getPrivateMethodInvoker($this->twig, 'addFunctions');
        $addFunctions();

        $this->twig = $this->twig->getTwig();
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    public function testAnchor()
    {
        $actual = $this->twig->render(
            'anchor',
            [
                'uri' => 'news/local/123',
                'title' => 'My News',
                'attributes' => ['title' => 'The best news!']
            ]
        );

        $expected = '<a href="http://localhost/index.php/news/local/123" title="The best news!">My News</a>';
        $this->assertEquals($expected, $actual);

        $actual = $this->twig->render(
            'anchor',
            [
                'uri' => 'news/local/123',
                'title' => '<s>abc</s>',
                'attributes' => ['<s>name</s>' => '<s>val</s>']
            ]
        );

        $expected = '<a href="http://localhost/index.php/news/local/123" &lt;s&gt;name&lt;/s&gt;="&lt;s&gt;val&lt;/s&gt;">&lt;s&gt;abc&lt;/s&gt;</a>';
        $this->assertEquals($expected, $actual);
    }

    public function testBaseUrl()
    {
        $actual = $this->twig->render('base_url');
        $expected = 'http://localhost/%22%3E%3Cs%3Eabc%3C/s%3E%3Ca%20name=%22test';
        $this->assertEquals($expected, $actual);
    }

    public function testSiteUrl()
    {
        $actual = $this->twig->render('site_url');
        $expected = 'http://localhost/index.php/%22%3E%3Cs%3Eabc%3C/s%3E%3Ca%20name=%22test';
        $this->assertEquals($expected, $actual);
    }
}
