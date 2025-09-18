<?php

namespace Tests\Twig;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Twig\Twig;

/**
 * @internal
 */
final class TwigCompileIndexTest extends CIUnitTestCase
{
    public function testCompileIndexPersists(): void
    {
        $cfg        = new \Daycry\Twig\Config\Twig();
        $cfg->paths = [__DIR__ . '/../_support/Templates'];
        $twig       = new Twig($cfg);
        $idxPath    = $twig->getCompileIndexPath();
        if (is_file($idxPath)) {
            @unlink($idxPath);
        }
        $this->assertFileDoesNotExist($idxPath);
        $twig->warmup(['welcome']);
        $this->assertFileExists($idxPath);
        $json = json_decode(file_get_contents($idxPath), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('welcome', $json);
        // New instance should load index
        $twig2 = new Twig($cfg);
        $this->assertTrue(in_array('welcome', array_column($twig2->listTemplates(true), 'name'), true));
    }
}
