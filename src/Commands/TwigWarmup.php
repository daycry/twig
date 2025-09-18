<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Twig\Config\Services;
use Daycry\Twig\Twig;

class TwigWarmup extends BaseCommand
{
    protected $group       = 'Twig';
    protected $name        = 'twig:warmup';
    protected $description = 'Precompiles (warms) Twig templates to reduce first-request latency.';
    protected $usage       = 'twig:warmup [template1 template2 ...] [--all] [--force]';
    protected $arguments   = [
        'templates' => 'Optional list of logical template names (without extension). If omitted, use --all to warm every discovered template.'
    ];
    protected $options     = [
        '--all'   => 'Warm all templates discovered in configured paths.',
        '--force' => 'Force recompilation even if a compiled cache file seems present.'
    ];

    public function run(array $params)
    {
        $force = in_array('--force', $params, true) || CLI::getOption('force');
        $all   = in_array('--all', $params, true) || CLI::getOption('all');

        /** @var Twig $twig */
        $twig = Services::twig();

        if ($all) {
            $result = $twig->warmupAll($force);
            CLI::write("Warmup (all): compiled={$result['compiled']} skipped={$result['skipped']} errors={$result['errors']}", 'green');
            return;
        }

        // Remove option flags from params to leave only template names
        $templates = array_values(array_filter($params, static fn($p) => strpos($p, '--') !== 0));
        if (empty($templates)) {
            CLI::error('Provide template names or use --all');
            return;
        }
        $result = $twig->warmup($templates, $force);
        CLI::write("Warmup: compiled={$result['compiled']} skipped={$result['skipped']} errors={$result['errors']}", 'green');
    }
}
