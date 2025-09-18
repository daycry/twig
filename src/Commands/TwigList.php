<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Twig\Config\Services;
use Daycry\Twig\Twig;

class TwigList extends BaseCommand
{
    protected $group       = 'Twig';
    protected $name        = 'twig:list';
    protected $description = 'Lists discovered Twig logical template names (optionally with compiled status).';
    protected $usage       = 'twig:list [--status]';
    protected $options     = [
        '--status' => 'Include compiled status (yes/no).'
    ];

    public function run(array $params)
    {
        $withStatus = in_array('--status', $params, true) || CLI::getOption('status');
        /** @var Twig $twig */
        $twig = Services::twig();
        $list = $twig->listTemplates($withStatus);
        if (empty($list)) {
            CLI::write('No templates found.', 'yellow');
            return;
        }
        if (!$withStatus) {
            foreach ($list as $name) {
                CLI::write($name);
            }
            CLI::write('Total: '.count($list));
            return;
        }
        $compiledCount = 0;
        foreach ($list as $row) {
            $compiled = $row['compiled'] ? 'yes' : 'no';
            if ($row['compiled']) { $compiledCount++; }
            CLI::write(str_pad($row['name'], 50).' '.$compiled);
        }
        CLI::write('Total: '.count($list).' Compiled: '.$compiledCount);
    }
}
