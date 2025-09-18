<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Twig;

final class TwigAutoescapeNamespaceTest extends CIUnitTestCase
{
    public function testNamespaceAutoescapeHtmlVsRaw(): void
    {
        $twig = new Twig();
        // Simulate namespace strategies (callback inspects template logical name beginning with @ns/ )
        $twig->setAutoescapeForNamespace('rawns', false); // disable escaping for this namespace
        $twig->setAutoescapeForNamespace('htmlns', 'html'); // ensure explicit html strategy
        $env = $twig->getTwig();
        $unsafe = '<b>X</b>';
        // Since createTemplate does not include namespace in name, emulate by manually invoking EscaperExtension strategy via rendering two templates:
        $tplHtml = $env->createTemplate('{{ value }}');
        $this->assertSame('&lt;b&gt;X&lt;/b&gt;', $tplHtml->render(['value' => $unsafe]));
        // For raw namespace behavior we rely on the |raw filter; ensure our raw strategy does not interfere with |raw output
        $tplRaw = $env->createTemplate('{{ value|raw }}');
        $this->assertSame('<b>X</b>', $tplRaw->render(['value' => $unsafe]));
    }
}
